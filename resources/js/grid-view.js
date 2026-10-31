(function () {
    'use strict';

    var requests = new Map();

    function gridFromElement(element) {
        return element && element.closest('[data-grid-view-ajax]');
    }

    function replaceGrid(grid, html, url, pushHistory) {
        var id = grid.dataset.gridViewId;
        // the middleware answers with the grid only; a full page works too
        var documentFragment = document.createRange().createContextualFragment(html);
        var replacements = Array.prototype.filter.call(
            documentFragment.querySelectorAll('[data-grid-view-id]'),
            function (candidate) {
                return candidate.dataset.gridViewId === id;
            }
        );
        var replacement = replacements.length === 1 ? replacements[0] : null;

        if (!replacement) {
            throw new Error('Grid response does not contain the expected grid.');
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
        headers[grid.dataset.gridViewHeader || 'X-Grid-View'] = id;

        request.promise = fetch(requestUrl, {
            credentials: 'same-origin',
            headers: headers,
            signal: controller.signal
        })
            .then(function (response) {
                if (!response.ok || response.redirected) {
                    throw new Error('Grid request failed with status ' + response.status + '.');
                }

                var contentType = response.headers.get('Content-Type') || '';

                if (!contentType.toLowerCase().includes('text/html')) {
                    throw new Error('Grid response must contain HTML.');
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
        var link = event.target.closest('a[data-grid-view-link], [data-grid-view-pagination] a');
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

        var params = new URLSearchParams(formData);
        var currentParams = new URL(window.location.href).searchParams;
        [grid.dataset.gridViewSortParameter, grid.dataset.gridViewDirectionParameter].forEach(function (name) {
            if (name && !params.has(name) && currentParams.has(name)) {
                params.set(name, currentParams.get(name));
            }
        });

        var url = new URL(form.action || window.location.href, window.location.href);
        url.search = params.toString();

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

    // Reordering (the "reorder" option): drag a record by its handle or press ↑ ↓ on a focused handle.
    // The new order goes to the reorder URL as {"ids": [...]}, one request at a time; on failure the grid is redrawn.
    var reorderSaves = new WeakMap();

    function reorderGrid(element) {
        return element && element.closest('[data-grid-view-reorder-url]');
    }

    function recordOf(element) {
        return element && element.closest('tbody[data-grid-view-key]');
    }

    function recordKeys(grid) {
        return Array.prototype.map.call(grid.querySelectorAll('tbody[data-grid-view-key]'), function (record) {
            return record.dataset.gridViewKey;
        });
    }

    function sendOrder(grid) {
        var token = document.querySelector('meta[name="csrf-token"]');

        return fetch(grid.dataset.gridViewReorderUrl, {
            method: grid.dataset.gridViewReorderMethod || 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token ? token.content : ''
            },
            body: JSON.stringify({ ids: recordKeys(grid) })
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Reorder request failed with status ' + response.status + '.');
            }
        });
    }

    function saveOrder(grid) {
        var previous = reorderSaves.get(grid) || Promise.resolve();
        var request = previous.then(function () {
            return sendOrder(grid);
        });

        reorderSaves.set(grid, request.catch(function () {}));
        request.then(function () {
            document.dispatchEvent(new CustomEvent('grid-view:reordered', {
                detail: { id: grid.dataset.gridViewId, grid: grid, keys: recordKeys(grid) }
            }));
        }, function () {
            document.dispatchEvent(new CustomEvent('grid-view:reorder-failed', {
                detail: { id: grid.dataset.gridViewId, grid: grid }
            }));
            // the saved order wins
            loadGrid(grid, window.location.href, false);
        });
    }

    var SLIDE = 'transform 0.15s ease';
    var DRAG_EDGE = 48;

    function recordsOf(record) {
        return Array.prototype.filter.call(record.parentNode.children, function (element) {
            return element.matches('tbody[data-grid-view-key]');
        });
    }

    function nextRecord(record) {
        var next = record.nextElementSibling;

        return next && next.matches('tbody[data-grid-view-key]') ? next : null;
    }

    // FLIP: records jump to their new place in the DOM and slide there from where they were seen;
    // the dragged record (except) follows the pointer on its own
    function slide(record, except, move) {
        var list = recordsOf(record).filter(function (element) {
            return element !== except;
        });
        var tops = list.map(function (element) {
            return element.getBoundingClientRect().top;
        });

        move();
        list.forEach(function (element, index) {
            element.style.transition = 'none';
            element.style.transform = '';

            var shift = tops[index] - element.getBoundingClientRect().top;

            if (shift) {
                element.style.transform = 'translateY(' + shift + 'px)';
                element.getBoundingClientRect();
                element.style.transition = SLIDE;
                element.style.transform = '';
            }
        });
    }

    // Drag by the handle: the record follows the pointer, the others slide aside, near the window edge the page scrolls.
    // The place is found by layout positions (offsetTop), so sliding records never confuse it.
    // Listeners are on the document: moving the record in the DOM drops the pointer capture of its handle.
    document.addEventListener('pointerdown', function (event) {
        var handle = event.target.closest('[data-grid-view-handle]');
        var grid = reorderGrid(handle);
        var record = recordOf(handle);

        if (!grid || !record || event.button !== 0 || grid.getAttribute('aria-busy') === 'true') {
            return;
        }

        var order = recordKeys(grid).join(',');
        var startY = event.clientY;
        var startTop = record.offsetTop;
        var startScroll = window.scrollY;
        var pointerY = startY;
        var frame = 0;

        function place() {
            var list = recordsOf(record);
            var first = list[0];
            var last = list[list.length - 1];
            var top = Math.min(
                Math.max(startTop + pointerY - startY + window.scrollY - startScroll, first.offsetTop),
                last.offsetTop + last.offsetHeight - record.offsetHeight
            );
            var middle = top + record.offsetHeight / 2;
            var before = list.find(function (element) {
                return element !== record && element.offsetTop + element.offsetHeight / 2 > middle;
            }) || null;

            if (before !== nextRecord(record)) {
                slide(record, record, function () {
                    record.parentNode.insertBefore(record, before || last.nextSibling);
                });
            }

            record.style.transform = 'translateY(' + (top - record.offsetTop) + 'px)';
        }

        function scroll() {
            var speed = 0;

            if (pointerY < DRAG_EDGE) {
                speed = -(DRAG_EDGE - pointerY) / 3;
            } else if (pointerY > window.innerHeight - DRAG_EDGE) {
                speed = (pointerY - window.innerHeight + DRAG_EDGE) / 3;
            }

            if (speed) {
                window.scrollBy(0, speed);
                place();
            }

            frame = window.requestAnimationFrame(scroll);
        }

        function move(moveEvent) {
            if (moveEvent.pointerId !== event.pointerId) {
                return;
            }

            // the button was released outside the window
            if (moveEvent.buttons === 0) {
                end(moveEvent);

                return;
            }

            pointerY = moveEvent.clientY;
            place();
        }

        function end(endEvent) {
            if (endEvent.pointerId !== event.pointerId) {
                return;
            }

            document.removeEventListener('pointermove', move);
            document.removeEventListener('pointerup', end);
            document.removeEventListener('pointercancel', end);
            window.cancelAnimationFrame(frame);
            grid.classList.remove('lgv-reordering');
            record.style.transition = SLIDE;
            record.style.transform = '';
            window.setTimeout(function () {
                record.classList.remove('lgv-dragging');
                recordsOf(record).forEach(function (element) {
                    element.style.transition = '';
                });
            }, 150);

            if (recordKeys(grid).join(',') !== order) {
                saveOrder(grid);
            }
        }

        event.preventDefault();
        grid.classList.add('lgv-reordering');
        record.classList.add('lgv-dragging');
        record.style.transition = 'none';
        document.addEventListener('pointermove', move);
        document.addEventListener('pointerup', end);
        document.addEventListener('pointercancel', end);
        frame = window.requestAnimationFrame(scroll);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') {
            return;
        }

        var handle = event.target.closest && event.target.closest('[data-grid-view-handle]');
        var grid = reorderGrid(handle);
        var record = recordOf(handle);

        if (!grid || !record) {
            return;
        }

        var up = event.key === 'ArrowUp';
        var sibling = up ? record.previousElementSibling : record.nextElementSibling;

        event.preventDefault();

        if (!sibling || !sibling.matches('tbody[data-grid-view-key]')) {
            return;
        }

        slide(record, null, function () {
            record.parentNode.insertBefore(record, up ? sibling : sibling.nextSibling);
        });
        handle.focus();
        saveOrder(grid);
    });

    window.addEventListener('popstate', function () {
        document.querySelectorAll('[data-grid-view-ajax]').forEach(function (grid) {
            loadGrid(grid, window.location.href, false);
        });
    });
}());
