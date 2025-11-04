(function () {
    'use strict';

    function assignRepoDetails(event) {
        var button = event.currentTarget;
        var urlInput = document.getElementById('bokun_repository_url');
        var branchInput = document.getElementById('bokun_repository_branch');

        if (!button || !urlInput || !branchInput) {
            return;
        }

        var repoUrl = button.getAttribute('data-url');
        var branch = button.getAttribute('data-branch');

        if (repoUrl) {
            urlInput.value = repoUrl;
            urlInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (branch) {
            branchInput.value = branch;
            branchInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function handleManagedRepoSelect(event) {
        var select = event.currentTarget;
        var urlTarget = select.getAttribute('data-url-target');
        var branchTarget = select.getAttribute('data-branch-target');
        var selectedOption = select.options[select.selectedIndex];

        if (urlTarget) {
            var urlInput = document.getElementById(urlTarget);

            if (urlInput) {
                urlInput.value = select.value;
                urlInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        if (branchTarget && selectedOption) {
            var defaultBranch = selectedOption.getAttribute('data-default-branch');

            if (defaultBranch) {
                var branchInput = document.getElementById(branchTarget);

                if (branchInput) {
                    branchInput.value = defaultBranch;
                    branchInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        }
    }

    function removeManagedPluginRow(event) {
        event.preventDefault();

        var button = event.currentTarget;
        var row = button.closest('.github-managed-plugin-row');

        if (!row) {
            return;
        }

        var tbody = row.parentNode;
        tbody.removeChild(row);

        if (!tbody.querySelector('.github-managed-plugin-row')) {
            addManagedPluginRow();
        }
    }

    function getNextRowIndex(tbody) {
        var rows = tbody.querySelectorAll('.github-managed-plugin-row');
        var maxIndex = -1;

        rows.forEach(function (row) {
            var select = row.querySelector('.github-managed-plugin-select');

            if (!select || !select.name) {
                return;
            }

            var match = select.name.match(/managed_plugins\[(\d+)\]/);

            if (!match) {
                return;
            }

            var index = parseInt(match[1], 10);

            if (!isNaN(index) && index > maxIndex) {
                maxIndex = index;
            }
        });

        return maxIndex + 1;
    }

    function wireRowEvents(row) {
        var removeButton = row.querySelector('.github-remove-managed-plugin');

        if (removeButton) {
            removeButton.addEventListener('click', removeManagedPluginRow);
        }

        var repoSelect = row.querySelector('.github-managed-repo-select');

        if (repoSelect && !repoSelect.dataset.initialized) {
            repoSelect.addEventListener('change', handleManagedRepoSelect);
            repoSelect.dataset.initialized = 'true';
        }
    }

    function addManagedPluginRow() {
        var template = document.getElementById('github-managed-plugin-row-template');
        var tbody = document.querySelector('.github-managed-plugins-rows');

        if (!template || !tbody) {
            return;
        }

        var index = getNextRowIndex(tbody);
        var html = template.innerHTML.replace(/__index__/g, index);
        var container = document.createElement('tbody');

        container.innerHTML = html;

        var newRow = container.firstElementChild;

        if (!newRow) {
            return;
        }

        tbody.appendChild(newRow);
        wireRowEvents(newRow);
    }

    function initManagedPlugins() {
        var rows = document.querySelectorAll('.github-managed-plugin-row');

        rows.forEach(function (row) {
            wireRowEvents(row);
        });

        var addButton = document.getElementById('github-add-managed-plugin');

        if (addButton) {
            addButton.addEventListener('click', function (event) {
                event.preventDefault();
                addManagedPluginRow();
            });
        }
    }

    function init() {
        var repoButtons = document.querySelectorAll('.bokun-select-repo');

        if (repoButtons.length) {
            repoButtons.forEach(function (button) {
                button.addEventListener('click', assignRepoDetails);
            });
        }

        initManagedPlugins();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
