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

    function init() {
        var repoButtons = document.querySelectorAll('.bokun-select-repo');

        if (!repoButtons.length) {
            return;
        }

        repoButtons.forEach(function (button) {
            button.addEventListener('click', assignRepoDetails);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
