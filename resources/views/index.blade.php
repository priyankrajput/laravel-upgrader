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
                    <form id="analyzeForm" class="flex items-center gap-2" onsubmit="return analyzeCompatibility(event)">
                        <select id="targetLaravel" class="px-2 py-1.5 text-sm border border-white/30 rounded-lg bg-white/10 text-white">
                            <option value="">Current Laravel</option>
                            <option value="9.0" class="text-gray-500">Laravel 9</option>
                            <option value="10.0" class="text-gray-500">Laravel 10</option>
                            <option value="11.0" class="text-gray-500">Laravel 11</option>
                            <option value="12.0" class="text-gray-500">Laravel 12</option>
                        </select>
                        <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-white/30 text-sm font-medium rounded-lg text-white bg-white/10 hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                            <i class="fas fa-magnifying-glass mr-1"></i>
                            Analyze
                        </button>
                        <button type="button" onclick="startChunkedAnalyze()" class="inline-flex items-center px-3 py-1.5 border border-white/30 text-sm font-medium rounded-lg text-white bg-white/10 hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                            <i class="fas fa-list-check mr-1"></i>
                            Analyze (Chunked)
                        </button>
                    </form>
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
                                    
                                    <button type="button" class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50" onclick="removePackageConfirm('{{ $package }}')" title="Remove package">
                                        <i class="fas fa-trash"></i>
                                    </button>
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
                            I understand a backup will be created automatically
                        </label>
                    </div>
                    <div class="flex space-x-3">
                        <button type="button" onclick="showBackups()" class="inline-flex items-center px-4 py-2 border border-orange-600 text-sm font-medium rounded-md text-orange-600 hover:bg-orange-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                            <i class="fas fa-history mr-2"></i>
                            Backups & Restore
                        </button>
                        <button type="submit" id="upgradeButton" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed" {{ $hasUpdates ? '' : 'disabled' }}>
                            <i class="fas fa-sync-alt mr-2"></i>
                            Update Selected
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upgrade Progress Overlay -->
<div id="loader-overlay" class="hidden fixed inset-0 bg-white/90 z-50 flex items-center justify-center">
    <div class="w-full max-w-xl px-6">
        <div class="text-center font-semibold text-gray-800 mb-4">Updating packages, please wait...</div>
        <div class="w-full bg-gray-200 rounded h-10 overflow-hidden">
            <div class="h-full w-full bg-blue-600 animate-pulse flex items-center justify-center text-white text-sm">
                Processing...
            </div>
        </div>
        <pre id="upgrade-log" class="hidden mt-4 w-full max-h-60 bg-gray-900 text-green-200 rounded p-4 overflow-auto text-sm"></pre>
    </div>
    <style>
        /* Prevent background scroll while overlay visible */
        body.overlay-open { overflow: hidden; }
    </style>
    <script>
        // Helper to toggle body scroll lock
        function setOverlayOpen(open){
            const b=document.body; if(!b) return; open? b.classList.add('overlay-open') : b.classList.remove('overlay-open');
        }
    </script>
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
        // Overlay & log elements
        const loader = document.getElementById('loader-overlay');
        const logBox = document.getElementById('upgrade-log');
        const logUrl = '{{ route('upgrader.log') }}';
        let pollInterval = null;
        let isPolling = false;
        
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
        
        // Start polling the upgrade log
        function pollUpgradeLog() {
            if (isPolling) return;
            isPolling = true;
            
            pollInterval = setInterval(() => {
                fetch('{{ route("upgrader.log") }}')
                    .then(response => response.text())
                    .then(data => {
                        const logArea = document.getElementById('upgrade-log');
                        if (logArea) {
                            logArea.textContent = data;
                            logArea.scrollTop = logArea.scrollHeight;
                        }
                        
                        // Check for completion indicators in the log
                        if (data.includes('Nothing to modify in lock file') || 
                            data.includes('Package operations:') ||
                            data.includes('Generating optimized autoload files') ||
                            data.includes('> Illuminate\\Foundation\\ComposerScripts::postAutoloadDump')) {
                            // Success indicators found
                            stopPollingAndShowResult('success', 'Upgrade completed successfully!');
                        } else if (data.includes('Installation failed') ||
                                   data.includes('Your requirements could not be resolved') ||
                                   data.includes('Fatal error') ||
                                   data.includes('Error:') ||
                                   data.includes('Exception:')) {
                            // Error indicators found
                            stopPollingAndShowResult('error', 'Upgrade failed. Check the log for details.');
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching log:', error);
                        stopPollingAndShowResult('error', 'Failed to fetch upgrade log.');
                    });
            }, 2000);
        }

        function stopPollingAndShowResult(status, message) {
            // Stop polling
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
            isPolling = false;
            
            // Hide overlay after a short delay to let user see final log
            setTimeout(() => {
                hideOverlay();
                showCompletionModal(status, message);
            }, 2000);
        }

        function hideOverlay() {
            const overlay = document.getElementById('loader-overlay');
            if (overlay) {
                overlay.classList.add('hidden');
            }
            // Re-enable upgrade button
            const upgradeBtn = document.getElementById('upgradeButton');
            if (upgradeBtn) {
                upgradeBtn.disabled = false;
            }
        }

        // Make these functions global so onclick can access them
        window.closeCompletionModal = function() {
            const modal = document.getElementById('completion-modal');
            if (modal) {
                modal.remove();
            }
            // Refresh page on success to show updated package versions
            location.reload();
        };

        window.showBackups = function() {
            fetch('{{ route("upgrader.backups") }}', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                showBackupsModal(data.backups);
            })
            .catch(error => {
                console.error('Error fetching backups:', error);
                alert('Failed to load backups');
            });
        };

        function showCompletionModal(status, message) {
            const isSuccess = status === 'success';
            
            // Get final log content for display
            let logContent = '';
            const logArea = document.getElementById('upgrade-log');
            if (logArea && logArea.textContent) {
                const lines = logArea.textContent.split('\n');
                // Show last 10 lines of log
                logContent = lines.slice(-10).join('\n');
            }
            
            const modalHtml = `
                <div id="completion-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
                    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                        <div class="p-6">
                            <div class="flex items-center mb-4">
                                <div class="flex-shrink-0">
                                    ${isSuccess ? 
                                        '<svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' :
                                        '<svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"></path></svg>'
                                    }
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-lg font-medium text-gray-900">
                                        ${isSuccess ? 'Upgrade Complete!' : 'Upgrade Failed'}
                                    </h3>
                                    <p class="text-sm text-gray-600">${message}</p>
                                </div>
                            </div>
                            
                            ${logContent ? `
                                <div class="mb-6">
                                    <h4 class="text-sm font-medium text-gray-900 mb-2">Final Output:</h4>
                                    <div class="bg-gray-100 rounded-lg p-3 text-xs font-mono text-gray-800 max-h-40 overflow-y-auto">
                                        <pre>${logContent}</pre>
                                    </div>
                                </div>
                            ` : ''}
                            
                            <div class="flex justify-end space-x-3">
                                ${!isSuccess ? 
                                    '<button onclick="closeCompletionModal(); showBackups();" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded text-sm">View Backups</button>' : 
                                    ''
                                }
                                <button onclick="closeCompletionModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
                                    ${isSuccess ? 'Refresh Page' : 'Close'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }

       
        // Enhanced submit: async start + overlay + log polling
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to update the selected packages? This action cannot be undone.')) {
                return false;
            }
            // Show overlay
            const overlay = document.getElementById('loader-overlay');
            if (overlay) {
                overlay.classList.remove('hidden');
            }
            upgradeButton.disabled = true;
            upgradeButton.innerHTML = '<svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Updating...';

            const formData = new FormData(form);
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    body: formData
                });
                console.log(res);
                
                if (res.ok) {
                    const data = await res.json();
                    if (data.status === 'started') {
                        // Start polling the upgrade log
                        pollUpgradeLog();
                    } else {
                        hideOverlay();
                        showCompletionModal('error', data.message || 'Failed to start upgrade');
                    }
                } else {
                    hideOverlay();
                    showCompletionModal('error', 'Failed to start upgrade. Server error.');
                }
            } catch (err) {
                hideOverlay();
                console.log(err);
                showCompletionModal('error', 'Upgrade request failed to start. Please check server logs.');
            }
            return false;
        });

        // If page reloaded during an in-progress upgrade, show overlay and start polling
        if (window.location.hash === '#upgrade-in-progress') {
            const overlay = document.getElementById('loader-overlay');
            if (overlay) {
                overlay.classList.remove('hidden');
            }
            pollUpgradeLog();
        }
    });
    
    // Modal functions - make global for onclick access
    window.openChangelogModal = function(packageId) {
        document.getElementById('changelog-modal-' + packageId).classList.remove('hidden');
    };
    
    window.closeChangelogModal = function(packageId) {
        document.getElementById('changelog-modal-' + packageId).classList.add('hidden');
    };
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('bg-opacity-50')) {
            event.target.classList.add('hidden');
        }
    });
    
    // Analyze compatibility for target Laravel
    window.analyzeCompatibility = function(e){
        e.preventDefault();
        const target = document.getElementById('targetLaravel').value;
        fetch('{{ route("upgrader.analyze") }}',{
            method:'POST',
            headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').getAttribute('content')},
            body: JSON.stringify({ target_laravel: target || null })
        }).then(r=>r.json()).then(data=>{
            showAnalysisModal(data);
        }).catch(()=>alert('Failed to analyze compatibility'));
        return false;
    };

    function showAnalysisModal(data){
        const incompatible = Object.values(data.packages||{}).filter(p=>p && p.compatibility && !p.compatibility.compatible);
        const html = `
        <div id="analysis-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-lg shadow-xl max-w-5xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Compatibility Analysis ${data.target_laravel?`for Laravel ${data.target_laravel}`:''}</h3>
                    <button onclick="closeAnalysisModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="p-6">
                    <div class="mb-4 text-sm text-gray-700">Incompatible packages: <strong>${incompatible.length}</strong> of ${Object.keys(data.packages||{}).length}</div>
                    ${Array.isArray(data.skipped) && data.skipped.length ? `
                    <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                        <div class="text-sm text-yellow-800 font-medium mb-1">Skipped / Manual review (${data.skipped.length}):</div>
                        <div class="text-xs text-yellow-800">${data.skipped.map(s=>`${s.name} ${s.reason?`- ${s.reason}`:''}`).join('<br>')}</div>
                    </div>` : ''}
                    <div class="divide-y">
                        ${Object.values(data.packages||{}).map(p=> p ? `
                            <div class="py-3 flex items-start justify-between">
                                <div>
                                    <div class="font-medium ${p.compatibility.compatible?'text-green-700':'text-red-700'}">${p.package}</div>
                                    <div class="text-xs text-gray-600">${p.recommended_action}</div>
                                    ${(p.compatibility.alternatives||[]).length?`<div class="text-xs text-gray-600 mt-1">Alternatives: ${p.compatibility.alternatives.join(', ')}</div>`:''}
                                </div>
                                ${!p.compatibility.compatible?`
                                <div class="flex gap-2">
                                    ${(p.compatibility.alternatives||[]).map(a=>`<button class=\"px-2 py-1 text-xs bg-blue-600 text-white rounded\" onclick=\"quickAddPackage('${a}')\">Add ${a}</button>`).join('')}
                                    <button class="px-2 py-1 text-xs bg-red-600 text-white rounded" onclick="removePackageConfirm('${p.package}')">Remove</button>
                                </div>`:''}
                            </div>` : '').join('')}
                    </div>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
    }
    window.closeAnalysisModal = function(){ const m=document.getElementById('analysis-modal'); if(m) m.remove(); };

    // Add/remove package helpers
    window.addPackagePrompt = function(pkg){
        const version = prompt(`Enter version/constraint for ${pkg} (e.g. ^1.2 or leave blank):`,'');
        fetch('{{ route("upgrader.packages.add") }}',{
            method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').getAttribute('content')},
            body: JSON.stringify({ package: pkg, version: version || null })
        }).then(r=>r.json()).then(()=>{ alert('Package updated in composer.json'); }).catch(()=>alert('Failed to update composer.json'));
    };
    window.quickAddPackage = function(pkg){ fetch('{{ route("upgrader.packages.add") }}',{ method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').getAttribute('content')}, body: JSON.stringify({ package: pkg })}).then(()=>alert(`Added ${pkg} to composer.json`)).catch(()=>alert('Failed to add package')); };
    window.removePackageConfirm = function(pkg){ if(!confirm(`Remove ${pkg} from composer.json?`)) return; fetch('{{ route("upgrader.packages.remove") }}',{ method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').getAttribute('content')}, body: JSON.stringify({ package: pkg })}).then(()=>alert('Package removed (if existed)')).catch(()=>alert('Failed to remove package')); };

    // Chunked analysis with progress
    window.startChunkedAnalyze = async function(){
        const target = document.getElementById('targetLaravel').value || null;
        // init
        const initRes = await fetch('{{ route("upgrader.analyze.init") }}',{ method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').getAttribute('content')}, body: JSON.stringify({ target_laravel: target })});
        if(!initRes.ok){ alert('Failed to init analysis'); return; }
        const init = await initRes.json();
        const pkgs = init.packages || [];
        if(pkgs.length===0){ alert('No packages to analyze'); return; }
        showAnalyzeProgress(pkgs.length);
        const results = [];
        const skipped = [];
        const withTimeout = (promise, ms) => new Promise((resolve, reject) => {
            const t = setTimeout(() => reject(new Error('timeout')), ms);
            promise.then(v => { clearTimeout(t); resolve(v); }).catch(e => { clearTimeout(t); reject(e); });
        });
        async function analyzeOne(p){
            const payload = { package: p.name, constraint: p.constraint, target_laravel: target };
            const doFetch = () => fetch('{{ route("upgrader.analyze.chunk") }}',{ method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').getAttribute('content')}, body: JSON.stringify(payload)});
            try{
                let res = await withTimeout(doFetch(), 12000);
                if(!res.ok) throw new Error('bad status');
                return await res.json();
            }catch(err){
                // one retry
                try{
                    let res = await withTimeout(doFetch(), 12000);
                    if(!res.ok) throw new Error('bad status');
                    return await res.json();
                }catch(e2){
                    skipped.push({ name: p.name, reason: e2 && e2.message ? e2.message : 'timeout/error' });
                    return { package: p.name, name: p.name, error: true };
                }
            }
        }
        for(let i=0;i<pkgs.length;i++){
            const p = pkgs[i];
            updateAnalyzeProgress(i+1, pkgs.length, p.name);
            const data = await analyzeOne(p);
            results.push(data);
            await new Promise(r=>setTimeout(r, 100)); // small delay to avoid 504
        }
        showAnalysisModal({ packages: Object.fromEntries(results.map(r=>[r.package||r.name||'unknown', r])) , target_laravel: target, skipped });
        closeAnalyzeProgress();
    };

    function showAnalyzeProgress(total){
        const html = `
        <div id="analyze-progress" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
                <div class="p-4 border-b text-sm font-medium">Analyzing packages...</div>
                <div class="p-4">
                    <div id="analyze-current" class="text-sm text-gray-700 mb-2">Starting...</div>
                    <div class="w-full bg-gray-200 rounded h-3 overflow-hidden"><div id="analyze-bar" class="h-3 bg-blue-600" style="width:0%"></div></div>
                    <div id="analyze-count" class="text-xs text-gray-500 mt-1">0 / ${total}</div>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
    }
    function updateAnalyzeProgress(done,total,name){
        const bar = document.getElementById('analyze-bar');
        const cnt = document.getElementById('analyze-count');
        const cur = document.getElementById('analyze-current');
        if(bar){ bar.style.width = Math.round(done*100/total)+"%"; }
        if(cnt){ cnt.textContent = `${done} / ${total}`; }
        if(cur){ cur.textContent = `Analyzing: ${name}`; }
    }
    function closeAnalyzeProgress(){ const m = document.getElementById('analyze-progress'); if(m) m.remove(); }

    // Show backups modal - already defined as window.showBackups above
    
    window.showBackupsModal = function(backups) {
        const modalHtml = `
            <div id="backups-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
                <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">Backups & Restore</h3>
                            <button onclick="closeBackupsModal()" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="p-6">
                        ${backups.length === 0 ? 
                            '<p class="text-gray-500 text-center py-8">No backups available</p>' :
                            `<div class="space-y-4">
                                ${backups.map(backup => `
                                    <div class="border border-gray-200 rounded-lg p-4">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h4 class="font-medium text-gray-900">${backup.id}</h4>
                                                <p class="text-sm text-gray-600">Created: ${new Date(backup.created_at).toLocaleString()}</p>
                                                <p class="text-sm text-gray-600">Packages: ${backup.packages.join(', ') || 'All packages'}</p>
                                            </div>
                                            <div class="flex space-x-2">
                                                <button onclick="restoreBackup('${backup.id}')" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">
                                                    Restore
                                                </button>
                                                <button onclick="deleteBackup('${backup.id}')" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">
                                                    Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>`
                        }
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    };

    window.closeBackupsModal = function() {
        const modal = document.getElementById('backups-modal');
        if (modal) {
            modal.remove();
        }
    };

    window.restoreBackup = function(backupId) {
        if (!confirm(`Are you sure you want to restore from backup ${backupId}? This will revert your packages to their previous versions.`)) {
            return;
        }

        fetch('{{ route("upgrader.restore") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                backup_id: backupId,
                confirm: 1
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'started') {
                closeBackupsModal();
                alert('Restore started. Monitor progress in the overlay.');
            } else {
                alert('Restore failed: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Restore failed: Network error');
        });
    };

    window.deleteBackup = function(backupId) {
        if (!confirm(`Are you sure you want to delete backup ${backupId}? This action cannot be undone.`)) {
            return;
        }

        fetch('{{ route("upgrader.delete-backup") }}', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                backup_id: backupId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Refresh the backups modal
                closeBackupsModal();
                showBackups();
            } else {
                alert('Delete failed: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Delete failed: Network error');
        });
    };
</script>

</body>
</html>