(function () {
    'use strict';

    function writeClipboard(link) {
        var textValue = link.dataset.copyValue || '';
        var htmlValue = link.dataset.copyHtml || '';

        if (navigator.clipboard) {
            if (htmlValue && typeof window.ClipboardItem !== 'undefined' && typeof Blob !== 'undefined') {
                try {
                    var item = new window.ClipboardItem({
                        'text/html': new Blob([htmlValue], { type: 'text/html' }),
                        'text/plain': new Blob([textValue], { type: 'text/plain' })
                    });

                    return navigator.clipboard.write([item]);
                } catch (error) {
                    // fall back to copying plain text below.
                }
            }

            if (navigator.clipboard.writeText) {
                return navigator.clipboard.writeText(textValue);
            }
        }

        return new Promise(function (resolve, reject) {
            var textarea = document.createElement('textarea');
            textarea.value = textValue;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';

            document.body.appendChild(textarea);
            textarea.select();

            try {
                if (document.execCommand('copy')) {
                    resolve();
                } else {
                    reject(new Error('Copy command was unsuccessful.'));
                }
            } catch (err) {
                reject(err);
            } finally {
                document.body.removeChild(textarea);
            }
        });
    }

    function updateState(link, state) {
        var label = link.dataset.copyLabel || link.textContent.trim();
        var done = link.dataset.copyDone || 'Copied!';
        var error = link.dataset.copyError || 'Copy failed';

        link.dataset.copyState = state;

        if (state === 'done') {
            link.textContent = done;
        } else if (state === 'error') {
            link.textContent = error;
        } else {
            link.textContent = label;
        }
    }

    function resetState(link) {
        updateState(link, 'default');
    }

    function handleCopy(event) {
        event.preventDefault();

        var link = event.currentTarget;

        if (link.dataset.copyState === 'loading') {
            return;
        }

        updateState(link, 'loading');

        writeClipboard(link)
            .then(function () {
                updateState(link, 'done');
                window.setTimeout(function () {
                    resetState(link);
                }, 2000);
            })
            .catch(function () {
                updateState(link, 'error');
                window.setTimeout(function () {
                    resetState(link);
                }, 2000);
            });
    }

    function enhanceLink(link) {
        if (!link || link.dataset.copyEnhanced === 'true') {
            return;
        }

        var label = link.dataset.copyLabel || link.textContent.trim() || 'Copy';
        link.dataset.copyLabel = label;
        link.textContent = label;
        link.setAttribute('role', 'button');
        link.setAttribute('data-copy-state', link.dataset.copyState || 'default');
        link.classList.add('bokun-booking-dashboard__copy-link');
        link.dataset.copyEnhanced = 'true';

        link.addEventListener('click', handleCopy);
        link.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                handleCopy({
                    preventDefault: function () {},
                    currentTarget: link
                });
            }
        });
    }

    function convertButton(button) {
        var link = document.createElement('a');
        var classNames = (button.className || '').split(/\s+/).filter(Boolean);

        // Remove WordPress button classes to avoid default button styling.
        classNames = classNames.filter(function (className) {
            return className !== 'button';
        });

        classNames.push('bokun-booking-dashboard__copy-link');
        link.className = classNames.join(' ');
        link.href = '#';

        if (button.id) {
            link.id = button.id;
        }

        Array.prototype.slice.call(button.attributes).forEach(function (attribute) {
            if (attribute.name === 'type') {
                return;
            }

            if (attribute.name.indexOf('data-') === 0 || attribute.name.indexOf('aria-') === 0) {
                link.setAttribute(attribute.name, attribute.value);
            }
        });

        link.dataset.copyLabel = link.dataset.copyLabel || button.textContent.trim();

        enhanceLink(link);
        button.replaceWith(link);
    }

    function convertButtons() {
        var buttons = document.querySelectorAll('.bokun-booking-dashboard__copy-button');

        buttons.forEach(function (button) {
            convertButton(button);
        });
    }

    function enhanceExistingLinks() {
        var links = document.querySelectorAll('.bokun-booking-dashboard__copy-link');

        links.forEach(function (link) {
            enhanceLink(link);
        });
    }

    function init() {
        convertButtons();
        enhanceExistingLinks();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
