(function () {
    'use strict';

    var requests = new Map();

    function gridFromElement(element) {
        return element && element.closest('[data-grid-view-ajax]');
    }

    function replaceGrid(grid, html, url, pushHistory) {
        var id = grid.dataset.gridViewId;
        var documentFragment = document.createRange().createContextualFragment(html);
        var replacements = Array.prototype.filter.call(
            documentFragment.querySelectorAll('[data-grid-view-id]'),
            function (candidate) {
                return candidate.dataset.gridViewId === id;
            }
        );
        var replacement = replacements.length === 1 ? replacements[0] : null;

        if (!replacement || replacement.parentNode !== documentFragment) {
            throw new Error('Grid partial response does not contain the expected grid.');
        }

        var restoreFocus = grid.contains && grid.contains(document.activeElement);
        grid.replaceWith(replacement);

        if (restoreFocus && replacement.focus) {
            replacement.focus({ preventScroll: true });
        }

        if (pushHistory) {
            var state = Object.assign({}, window.history.state || {});
            state.laravelGridView = { id: id };
            window.history.pushState(state, '', url);
        }

        document.dispatchEvent(new CustomEvent('grid-view:rendered', {
            detail: {
                id: id,
                grid: replacement,
                url: url
            }
        }));
    }

    function loadGrid(grid, url, pushHistory) {
        var id = grid.dataset.gridViewId;
        var requestUrl = new URL(url, window.location.href).toString();
        var currentRequest = requests.get(id);

        if (currentRequest && currentRequest.url === requestUrl) {
            return currentRequest.promise;
        }

        if (currentRequest) {
            currentRequest.controller.abort();
        }

        var controller = new AbortController();
        var request = {
            controller: controller,
            url: requestUrl,
            promise: null
        };

        requests.set(id, request);
        grid.setAttribute('aria-busy', 'true');

        var headers = {
            'X-Requested-With': 'XMLHttpRequest'
        };
        headers[grid.dataset.gridViewPartialHeader || 'X-Grid-View-Partial'] = '1';
        headers[grid.dataset.gridViewPartialIdHeader || 'X-Grid-View-ID'] = id;

        request.promise = fetch(requestUrl, {
            credentials: 'same-origin',
            headers: headers,
            signal: controller.signal
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Grid request failed with status ' + response.status + '.');
                }

                if (
                    response.redirected
                    || response.headers.get(grid.dataset.gridViewPartialHeader || 'X-Grid-View-Partial') !== '1'
                    || response.headers.get(grid.dataset.gridViewPartialIdHeader || 'X-Grid-View-ID') !== id
                ) {
                    throw new Error('Grid response does not match the partial response contract.');
                }

                var contentType = response.headers.get('Content-Type') || '';

                if (!contentType.toLowerCase().includes('text/html')) {
                    throw new Error('Grid partial response must contain HTML.');
                }

                return response.text();
            })
            .then(function (html) {
                replaceGrid(grid, html, requestUrl, pushHistory);
            })
            .catch(function (error) {
                if (error.name !== 'AbortError') {
                    window.location.assign(requestUrl);
                }
            })
            .finally(function () {
                if (requests.get(id) === request) {
                    requests.delete(id);
                    grid.removeAttribute('aria-busy');
                }
            });

        return request.promise;
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('.lgv-sort, [data-grid-view-pagination] a');
        var grid = gridFromElement(link);

        if (!grid || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        var linkUrl = new URL(link.href, window.location.href);

        if (
            (link.target && link.target !== '_self')
            || (link.hasAttribute && link.hasAttribute('download'))
            || !['http:', 'https:'].includes(linkUrl.protocol)
            || linkUrl.origin !== new URL(window.location.href).origin
        ) {
            return;
        }

        event.preventDefault();

        if (grid.getAttribute('aria-busy') === 'true') {
            return;
        }

        loadGrid(grid, link.href, true);
    });

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('form[data-grid-confirm]');

        if (form && !window.confirm(form.dataset.gridConfirm)) {
            event.preventDefault();
        }
    });

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('form[data-grid-view-filter]');

        if (!form || event.defaultPrevented || form.method.toUpperCase() !== 'GET') {
            return;
        }

        var id = form.dataset.gridViewFilter;
        var grid = Array.prototype.find.call(
            document.querySelectorAll('[data-grid-view-ajax]'),
            function (candidate) {
                return candidate.dataset.gridViewId === id;
            }
        );

        if (!grid) {
            return;
        }

        if (grid.getAttribute('aria-busy') === 'true') {
            event.preventDefault();

            return;
        }

        var formData = new FormData(form);

        if (event.submitter && event.submitter.name) {
            formData.append(event.submitter.name, event.submitter.value);
        }

        var url = new URL(form.action || window.location.href, window.location.href);
        url.search = new URLSearchParams(formData).toString();

        event.preventDefault();
        loadGrid(grid, url.toString(), true);
    });

    document.addEventListener('click', function (event) {
        var control = event.target.closest('[data-grid-confirm]:not(form)');

        if (control && !window.confirm(control.dataset.gridConfirm)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    });

    window.addEventListener('popstate', function () {
        document.querySelectorAll('[data-grid-view-ajax]').forEach(function (grid) {
            loadGrid(grid, window.location.href, false);
        });
    });
}());
