<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Laravel Package Upgrader</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        secondary: '#64748b',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 font-sans antialiased">

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <!-- HEADER -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="flex items-center gap-4 flex-wrap">
                    <h1 class="text-xl font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-cubes"></i>
                        Laravel Package Upgrader
                    </h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $hasUpdates ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' }}">
                        {{ $hasUpdates ? 'Updates Available' : 'All packages up to date' }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-white/20 text-white">
                        Total: {{ count($availableUpdates ?? []) }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-white/20 text-white">
                        With updates: {{ collect($availableUpdates ?? [])->filter(fn($d) => $d['has_update'])->count() }}
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <input type="search" id="packageFilter" class="px-3 py-1.5 text-sm border border-white/30 rounded-lg bg-white/10 text-white placeholder-white/70 focus:outline-none focus:ring-2 focus:ring-white/50" placeholder="🔍 Search packages..." />
                    <form action="{{ route('upgrader.clear-cache') }}" method="POST">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-white/30 text-sm font-medium rounded-lg text-white bg-white/10 hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50 transition-colors" title="Clear cache and re-check">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="p-6">
            <!-- Alerts -->
            @if(session('success'))
                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-400 mr-3"></i>
                        <span class="text-green-800">{{ session('success') }}</span>
                    </div>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-times-circle text-red-400 mr-3"></i>
                        <span class="text-red-800">{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            <!-- Important Note -->
            <div class="mb-6 p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded-lg">
                <div class="flex">
                    <i class="fas fa-exclamation-triangle text-yellow-400 mr-3 mt-0.5"></i>
                    <div>
                        <h3 class="text-sm font-medium text-yellow-800">Important:</h3>
                        <ul class="mt-2 text-sm text-yellow-700 space-y-1">
                            <li>• Backup your database and files</li>
                            <li>• Check the <a href="https://laravel.com/docs/upgrade" target="_blank" class="underline hover:text-yellow-900">upgrade guide</a></li>
                            <li>• Run this in a maintenance window</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Upgrade Form -->
            <form method="POST" action="{{ route('upgrader.run') }}" id="upgradeForm">
                @csrf
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300" id="packagesTable">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12">
                                    <input type="checkbox" id="selectAll" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" {{ $hasUpdates ? '' : 'disabled' }}>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Package</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Available</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Target</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Constraint</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($availableUpdates as $package => $data)
                            <tr class="{{ $data['has_update'] ? 'bg-yellow-50' : 'hover:bg-gray-50' }} transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($data['has_update'])
                                        <input type="checkbox" name="packages[]" value="{{ $package }}" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded package-checkbox" {{ $data['selected'] ? 'checked' : '' }}>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <div class="flex items-center">
                                            <div class="text-sm font-medium text-gray-900">{{ $package }}</div>
                                            @if($data['is_major_update'])
                                                <span class="ml-2 text-red-500" title="Major version upgrade. May contain breaking changes.">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-sm text-gray-500">composer</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $data['current'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $data['target'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($data['has_update'])
                                        <select class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md target-select" name="target_versions[{{ $package }}]" {{ $data['selected'] ? '' : 'disabled' }}>
                                            @foreach($data['versions'] as $ver)
                                                <option value="{{ $ver }}" {{ $ver === $data['target'] ? 'selected' : '' }}>{{ $ver }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="text-sm text-gray-500">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <code class="px-2 py-1 text-xs bg-gray-100 rounded">{{ $data['constraint'] }}</code>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($data['has_update'])
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            Update Available
                                        </span>
                                        @if($data['is_major_update'])
                                            <br>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 mt-1" title="Major version upgrade. May contain breaking changes.">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                Major Upgrade
                                            </span>
                                        @endif
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Up to date
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    @if($data['is_major_update'])
                                        <button type="button" class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500" onclick="autoFixMajorUpgrade('{{ $package }}')" title="Auto-fix breaking changes">
                                            <i class="fas fa-magic mr-1"></i>
                                            Auto-Fix
                                        </button>
                                    @endif
                                    @if(isset($changelogs[$package]))
                                        <button type="button" class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" onclick="openChangelogModal('{{ md5($package) }}')">
                                            <i class="fa-solid fa-clock-rotate-left"></i>
                                        </button>
                                    @endif
                                    <a href="https://packagist.org/packages/{{ $package }}" target="_blank" class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-box-open"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">No packages found to update.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex items-center justify-between">
                    <div class="flex items-center">
                        <input type="checkbox" id="confirm" name="confirm" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" required>
                        <label for="confirm" class="ml-2 block text-sm text-gray-900">
                            I have backed up my application and database
                        </label>
                    </div>
                    <button type="submit" id="upgradeButton" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed" {{ $hasUpdates ? '' : 'disabled' }}>
                        <i class="fas fa-sync-alt mr-2"></i>
                        Update Selected
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Changelog Modals -->
@if(isset($changelogs))
    @foreach($changelogs as $package => $releases)
        <div id="changelog-modal-{{ md5($package) }}" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Changelog · {{ $package }}</h3>
                        <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeChangelogModal('{{ md5($package) }}')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        @foreach($releases as $release)
                            <div class="mb-4 border-b border-gray-200 pb-4 last:border-b-0">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-sm font-semibold text-gray-900">v{{ $release['version'] }}</h4>
                                    <span class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($release['date'])->format('Y-m-d') }}</span>
                                </div>
                                <pre class="text-xs text-gray-700 bg-gray-50 p-3 rounded whitespace-pre-wrap">{{ $release['notes'] }}</pre>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endif

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Select all checkboxes
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.package-checkbox');
        const upgradeButton = document.getElementById('upgradeButton');
        const form = document.getElementById('upgradeForm');
        const filter = document.getElementById('packageFilter');
        const table = document.getElementById('packagesTable');
        
        // Package search filter
        if (filter) {
            filter.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    const packageCell = row.querySelector('td:nth-child(2)');
                    if (packageCell) {
                        const packageName = packageCell.textContent.toLowerCase();
                        row.style.display = packageName.includes(query) ? '' : 'none';
                    }
                });
            });
        }
        
        // Toggle all checkboxes
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateUpgradeButton();
            });
        }
        
        // Update "Select All" when individual checkboxes change
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (!this.checked && selectAll.checked) {
                    selectAll.checked = false;
                }
                updateUpgradeButton();
            });
        });
        
        // Enable/disable upgrade button based on selection
        function updateUpgradeButton() {
            const anyChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);
            upgradeButton.disabled = !anyChecked;
        }
        
        // Confirm before submitting
        form.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to update the selected packages? This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
            upgradeButton.disabled = true;
            upgradeButton.innerHTML = '<svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Updating...';
            return true;
        });
    });
    
    // Modal functions
    function openChangelogModal(packageId) {
        document.getElementById('changelog-modal-' + packageId).classList.remove('hidden');
    }
    
    function closeChangelogModal(packageId) {
        document.getElementById('changelog-modal-' + packageId).classList.add('hidden');
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('bg-opacity-50')) {
            event.target.classList.add('hidden');
        }
    });
    
    // Auto-fix major upgrade function
    function autoFixMajorUpgrade(packageName) {
        if (!confirm(`Are you sure you want to auto-fix breaking changes for ${packageName}? This will attempt to automatically update your code to be compatible with the new major version.`)) {
            return;
        }
        
        const button = event.target.closest('button');
        const originalContent = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Fixing...';
        
        fetch('{{ route("upgrader.auto-fix") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                package: packageName
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Auto-fix completed for ${packageName}!\n\nChanges made:\n${data.changes.join('\n')}`);
                location.reload(); // Refresh to show updated status
            } else {
                alert(`Auto-fix failed for ${packageName}: ${data.message}`);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(`Auto-fix failed for ${packageName}: Network error`);
        })
        .finally(() => {
            button.disabled = false;
            button.innerHTML = originalContent;
        });
    }
</script>

</body>
</html>