/* ========================================================================
   School Clinic IMS - Front-end helpers
   ======================================================================== */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Reset all modal forms when a modal is closed
        document.querySelectorAll('.modal').forEach(function (modal) {
            modal.addEventListener('hidden.bs.modal', function () {
                var form = modal.querySelector('form');
                if (form) {
                    form.reset();
                    // Also clear any validation error messages inside the modal
                    form.querySelectorAll('.alert-danger').forEach(function (alert) {
                        alert.remove();
                    });
                }
            });
        });

        var sidebar = document.getElementById('appSidebar');
        var toggle = document.getElementById('sidebarToggle');
        var brandControl = document.getElementById('sidebarBrandControl');
        var backdrop = document.getElementById('sidebarBackdrop');
        var wrapper = sidebar ? sidebar.closest('.app-wrapper') : null;
        var mobileQuery = window.matchMedia('(max-width: 991.98px)');
        // Remembered across pages: 'true' while the rail is collapsed.
        // One implementation serves every role.
        var sidebarStateKey = 'clinicSidebarCollapsed';

        function setToggleState(expanded) {
            if (toggle) {
                toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                toggle.setAttribute('aria-label', expanded ? 'Close navigation' : 'Open navigation');
                toggle.setAttribute('title', expanded ? 'Close navigation' : 'Open navigation');
            }
            document.body.classList.toggle('sidebar-menu-open', expanded && mobileQuery.matches);
        }

        function setBrandControlState(collapsed) {
            if (!brandControl) return;
            var label = collapsed ? 'Expand navigation' : 'Collapse navigation';
            brandControl.setAttribute('aria-label', label);
            brandControl.setAttribute('title', label);
        }

        function isSidebarCollapsed() {
            return !!(wrapper && wrapper.classList.contains('sidebar-collapsed'));
        }

        function openSidebar() {
            if (sidebar) sidebar.classList.add('show');
            if (backdrop) backdrop.classList.add('show');
            setToggleState(true);
        }
        function closeSidebar() {
            if (sidebar) sidebar.classList.remove('show');
            if (backdrop) backdrop.classList.remove('show');
            setToggleState(false);
        }

        // Single source of truth for the persistent desktop rail state: the
        // topbar hamburger, the sidebar's "<"/"»" control and hover all go
        // through here, so they can never disagree.
        function setSidebarCollapsed(collapsed) {
            if (!wrapper) return;
            wrapper.classList.toggle('sidebar-collapsed', collapsed);
            if (sidebar) sidebar.classList.remove('sidebar-hover-expanded');
            try {
                localStorage.setItem(sidebarStateKey, collapsed ? 'true' : 'false');
            } catch (e) {
                // Storage unavailable (private mode) — state stays in-memory.
            }
            setToggleState(!collapsed);
            setBrandControlState(collapsed);
        }
        if (toggle) toggle.addEventListener('click', function () {
            if (mobileQuery.matches) {
                // Below 992px the sidebar is an off-canvas drawer.
                if (sidebar && sidebar.classList.contains('show')) closeSidebar();
                else openSidebar();
                return;
            }
            setSidebarCollapsed(!isSidebarCollapsed());
        });
        if (backdrop) backdrop.addEventListener('click', closeSidebar);

        // Hovering the collapsed rail pops it out to full width. It STAYS
        // expanded after the pointer leaves; only "<" (sidebar) or the
        // hamburger (topbar) collapses it again.
        if (sidebar) {
            sidebar.addEventListener('mouseenter', function () {
                if (!mobileQuery.matches && isSidebarCollapsed()) setSidebarCollapsed(false);
            });
        }

        // "<" collapses an expanded sidebar, "»" expands a collapsed one.
        if (brandControl) brandControl.addEventListener('click', function () {
            if (mobileQuery.matches) return;
            setSidebarCollapsed(!isSidebarCollapsed());
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && mobileQuery.matches && sidebar && sidebar.classList.contains('show')) {
                closeSidebar();
                if (toggle) toggle.focus();
            }
        });
        // Restore the remembered desktop state ('true' = rail collapsed).
        setToggleState(!(wrapper && isSidebarCollapsed()));
        setBrandControlState(isSidebarCollapsed());
        if (!mobileQuery.matches && wrapper && localStorage.getItem(sidebarStateKey) === 'true') {
            wrapper.classList.add('sidebar-collapsed');
            setToggleState(false);
            setBrandControlState(true);
        }
        mobileQuery.addEventListener('change', function (event) {
            if (sidebar) sidebar.classList.remove('sidebar-hover-expanded');
            if (event.matches) {
                if (wrapper) wrapper.classList.remove('sidebar-collapsed');
                closeSidebar();
            } else {
                closeSidebar();
                if (wrapper && localStorage.getItem(sidebarStateKey) === 'true') {
                    wrapper.classList.add('sidebar-collapsed');
                }
                setToggleState(!isSidebarCollapsed());
                setBrandControlState(isSidebarCollapsed());
            }
        });

        document.querySelectorAll('.alert-auto-dismiss').forEach(function (el) {
            setTimeout(function () {
                el.style.transition = 'opacity .4s';
                el.style.opacity = '0';
                setTimeout(function () { el.remove(); }, 400);
            }, 5000);
        });

        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (ev) {
                if (!window.confirm(form.getAttribute('data-confirm'))) {
                    ev.preventDefault();
                }
            });
        });

        document.querySelectorAll('form[data-auto-submit]').forEach(function (form) {
            form.querySelectorAll('select, input[type="date"]').forEach(function (field) {
                field.addEventListener('change', function () { form.submit(); });
            });
        });

        document.querySelectorAll('a[data-confirm]').forEach(function (el) {
            el.addEventListener('click', function (ev) {
                var msg = el.getAttribute('data-confirm') || 'Are you sure?';
                if (!window.confirm(msg)) {
                    ev.preventDefault();
                }
            });
        });

        // ---- SweetAlert2: Delete confirmations ----
        document.querySelectorAll('a[data-swal-delete], button[data-swal-delete]').forEach(function (el) {
            el.addEventListener('click', function (ev) {
                ev.preventDefault();
                var itemName = el.getAttribute('data-swal-delete') || 'record';
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'You won\'t be able to revert this!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Deleted!',
                            text: itemName.charAt(0).toUpperCase() + itemName.slice(1) + ' has been deleted.',
                            icon: 'success'
                        });
                        setTimeout(function() {
                            if (el.tagName === 'A') {
                                window.location.href = el.href;
                            } else {
                                el.closest('form').submit();
                            }
                        }, 1000);
                    }
                });
            });
        });

        // ---- SweetAlert2: Save/Update confirmations ----
        document.querySelectorAll('form[data-swal-save], button[data-swal-save]').forEach(function (el) {
            el.addEventListener('submit', function (ev) {
                ev.preventDefault();
                var form = el.tagName === 'FORM' ? el : el.closest('form');
                var itemName = el.getAttribute('data-swal-save') || 'changes';
                Swal.fire({
                    title: 'Do you want to save the changes?',
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: 'Save',
                    denyButtonText: 'Don\'t save'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire('Saved!', itemName.charAt(0).toUpperCase() + itemName.slice(1) + ' has been saved.', 'success');
                        setTimeout(function() { form.submit(); }, 1000);
                    } else if (result.isDenied) {
                        Swal.fire('Changes are not saved', '', 'info');
                    }
                });
            });
        });

        // ---- SweetAlert2: Add confirmations ----
        document.querySelectorAll('button[data-swal-add], a[data-swal-add]').forEach(function (el) {
            el.addEventListener('click', function (ev) {
                ev.preventDefault();
                var itemName = el.getAttribute('data-swal-add') || 'record';
                var form = el.closest('form');
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'You won\'t be able to revert this!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, add it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Added!',
                            text: itemName.charAt(0).toUpperCase() + itemName.slice(1) + ' has been added.',
                            icon: 'success'
                        });
                        setTimeout(function() {
                            if (form) {
                                form.submit();
                            } else if (el.tagName === 'A') {
                                window.location.href = el.href;
                            }
                        }, 1000);
                    }
                });
            });
        });

        document.querySelectorAll('[data-delete-modal]').forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                var target = document.getElementById('confirmDeleteBatch');
                if (target) target.href = trigger.getAttribute('data-delete-modal');
            });
        });

        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function () {
                var submit = form.querySelector('button[type="submit"], button:not([type])');
                if (!submit || form.dataset.confirmed === 'false') return;
                submit.disabled = true;
                submit.dataset.originalLabel = submit.innerHTML;
                submit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Saving...';
            });
        });

        function debounce(fn, wait) {
            var timeout = null;
            return function () {
                var context = this;
                var args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function () {
                    fn.apply(context, args);
                }, wait || 250);
            };
        }

        document.querySelectorAll('[data-live-filter]').forEach(function (input) {
            var selector = input.getAttribute('data-live-filter');
            var rows = Array.prototype.slice.call(document.querySelectorAll(selector));
            var countTarget = document.getElementById(input.getAttribute('data-live-filter-count'));

            var applyFilter = debounce(function () {
                var query = (input.value || '').trim().toLowerCase();
                var visibleCount = 0;

                rows.forEach(function (row) {
                    var text = (row.textContent || '').toLowerCase();
                    var matches = query === '' || text.indexOf(query) !== -1;
                    row.classList.toggle('d-none', !matches);
                    if (matches) visibleCount += 1;
                });

                if (countTarget) {
                    countTarget.textContent = query ? visibleCount + ' match' + (visibleCount === 1 ? '' : 'es') : 'All records';
                }
            }, 200);

            input.addEventListener('input', applyFilter);
            applyFilter();
        });

        document.querySelectorAll('[data-search-trigger]').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.querySelector(button.getAttribute('data-search-trigger'));
                if (input) {
                    input.focus();
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        });

        document.querySelectorAll('[data-patient-combobox]').forEach(function (input) {
            var hidden = document.querySelector(input.getAttribute('data-patient-combobox'));
            var list = document.getElementById(input.getAttribute('list'));
            if (!hidden || !list) return;
            var options = Array.from(list.options);
            var syncPatient = function () {
                var value = input.value.trim().toLowerCase();
                var match = options.find(function (option) {
                    return option.value.trim().toLowerCase() === value;
                });
                if (!match && value) {
                    var matches = options.filter(function (option) {
                        return option.value.toLowerCase().indexOf(value) !== -1;
                    });
                    if (matches.length === 1) match = matches[0];
                }
                hidden.value = match ? match.dataset.userId : '';
            };
            input.addEventListener('input', function () {
                syncPatient();
            });
            input.addEventListener('change', syncPatient);
            input.addEventListener('blur', syncPatient);
            var form = input.closest('form');
            if (form) {
                form.addEventListener('submit', function (event) {
                    syncPatient();
                    if (!hidden.value) {
                        event.preventDefault();
                        input.setCustomValidity('Select a patient from the suggestions.');
                        input.reportValidity();
                    } else {
                        input.setCustomValidity('');
                    }
                });
                input.addEventListener('input', function () { input.setCustomValidity(''); });
            }
        });

        document.querySelectorAll('[data-export-table]').forEach(function (button) {
            button.addEventListener('click', function () {
                var table = document.querySelector(button.getAttribute('data-export-table'));
                if (!table) return;
                var rows = Array.from(table.querySelectorAll('tr')).map(function (row) {
                    return Array.from(row.querySelectorAll('th, td')).map(function (cell) {
                        return '"' + cell.textContent.trim().replace(/"/g, '""') + '"';
                    }).join(',');
                });
                var blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
                var link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = (button.getAttribute('data-export-name') || 'clinic-report') + '.csv';
                link.click();
                URL.revokeObjectURL(link.href);
            });
        });

        document.querySelectorAll('[data-profile-tab-button]').forEach(function (button) {
            button.addEventListener('click', function () {
                var target = button.getAttribute('data-profile-tab-button');
                var profileRow = document.querySelector('.patient-profile-row');
                if (profileRow) profileRow.classList.toggle('activity-view', target === 'activity');
                document.querySelectorAll('[data-profile-tab-button]').forEach(function (item) {
                    item.classList.toggle('active', item === button);
                });
                document.querySelectorAll('[data-profile-tab-panel]').forEach(function (panel) {
                    panel.classList.toggle('d-none', panel.getAttribute('data-profile-tab-panel') !== target);
                });
            });
        });

        document.querySelectorAll('form[data-autosave]').forEach(function (form) {
            var key = 'school-clinic-draft:' + form.getAttribute('data-autosave');
            var fields = form.querySelectorAll('input[name], select[name], textarea[name]');
            try {
                var saved = JSON.parse(localStorage.getItem(key) || '{}');
                fields.forEach(function (field) {
                    if (saved[field.name] !== undefined && saved[field.name] !== field.value) field.value = saved[field.name];
                    field.addEventListener('input', function () {
                        var draft = {};
                        fields.forEach(function (item) { draft[item.name] = item.value; });
                        localStorage.setItem(key, JSON.stringify(draft));
                    });
                });
                form.addEventListener('submit', function () { localStorage.removeItem(key); });
            } catch (err) {
                // Draft saving is optional and must never block form use.
            }
        });

        var timeoutModal = document.getElementById('sessionTimeoutModal');
        var continueSession = document.getElementById('continueSession');
        var countdown = document.getElementById('sessionCountdown');
        if (timeoutModal && window.bootstrap && continueSession) {
            var timeout = 55 * 60 * 1000;
            var warning = 5 * 60;
            var timer;
            var modal = new bootstrap.Modal(timeoutModal);
            var startTimeout = function () {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    var remaining = warning;
                    modal.show();
                    countdown.textContent = '5:00';
                    var interval = setInterval(function () {
                        remaining--;
                        if (remaining <= 0) {
                            clearInterval(interval);
                            window.location.href = document.body.dataset.logoutUrl || 'auth/logout.php';
                            return;
                        }
                        countdown.textContent = Math.floor(remaining / 60) + ':' + String(remaining % 60).padStart(2, '0');
                    }, 1000);
                    continueSession.onclick = function () {
                        clearInterval(interval);
                        modal.hide();
                        startTimeout();
                    };
                }, timeout);
            };
            startTimeout();
        }

        var bd = document.getElementById('birthdate');
        var age = document.getElementById('ageField');
        if (bd && age) {
            var calc = function () {
                if (!bd.value) { age.value = ''; return; }
                var d = new Date(bd.value), now = new Date();
                var a = now.getFullYear() - d.getFullYear();
                var m = now.getMonth() - d.getMonth();
                if (m < 0 || (m === 0 && now.getDate() < d.getDate())) a--;
                age.value = (a >= 0) ? a : '';
            };
            bd.addEventListener('change', calc);
            calc();
        }

        // ---- Notification Bell (SSE-based) --------------------------------
        var notifBellBtn = document.getElementById('notifBellBtn');
        var notifBadge = document.getElementById('notifBadge');
        var notifList = document.getElementById('notifList');
        var notifEventSource = null;

        function renderNotifIcon(severity) {
            var icons = {
                success: 'bi-check-circle-fill',
                warning: 'bi-exclamation-triangle-fill',
                danger: 'bi-x-octagon-fill',
                info: 'bi-bell-fill'
            };
            return icons[severity] || 'bi-bell-fill';
        }

        function renderNotifItem(n) {
            var unreadClass = n.is_read ? '' : 'fw-bold';
            var borderClass = n.is_read ? '' : 'border-start border-4 border-primary';
            var actionLink = n.action_url
                ? '<a href="' + n.action_url + '" class="btn btn-sm btn-outline-primary py-0 px-2 ms-2">View</a>'
                : '';
            var markRead = n.is_read
                ? ''
                : '<a href="#" class="text-muted small mark-read" data-id="' + n.id + '">Mark read</a>';
            return '<li class="dropdown-item-text px-3 py-2 ' + borderClass + '">' +
                '<div class="d-flex align-items-start gap-2">' +
                    '<i class="bi ' + renderNotifIcon(n.severity) + ' text-' + (n.severity === 'warning' ? 'warning' : n.severity) + '"></i>' +
                    '<div class="flex-grow-1">' +
                        '<div class="small ' + unreadClass + '">' + n.title + '</div>' +
                        '<div class="small text-muted">' + n.message.substring(0, 80) + (n.message.length > 80 ? '...' : '') + '</div>' +
                        '<div class="d-flex align-items-center gap-1 mt-1">' + actionLink + markRead + '</div>' +
                    '</div>' +
                    '<small class="text-muted flex-shrink-0">' + timeAgo(n.created_at) + '</small>' +
                '</div>' +
            '</li><li><hr class="dropdown-divider my-1"></li>';
        }

        function timeAgo(dateStr) {
            if (!dateStr) return '';
            var then = new Date(dateStr.replace(' ', 'T'));
            if (isNaN(then.getTime())) return '';
            var now = new Date();
            var diff = Math.floor((now - then) / 1000);
            if (diff < 60) return diff + 's ago';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            return Math.floor(diff / 86400) + 'd ago';
        }

        function updateNotifBadge(count) {
            if (!notifBadge) return;
            if (count > 0) {
                notifBadge.textContent = count > 99 ? '99+' : count;
                notifBadge.classList.remove('d-none');
            } else {
                notifBadge.classList.add('d-none');
            }
        }

        function addNotifItem(n) {
            if (!notifList) return;
            var emptyMsg = notifList.querySelector('.text-muted.py-3');
            if (emptyMsg) emptyMsg.remove();
            var html = renderNotifItem(n);
            notifList.insertAdjacentHTML('afterbegin', html);
        }

        // Absolute base for app API calls. Derived from this file's own URL so
        // requests work from any page depth (/admin/..., /staff/..., root ...).
        var appBase = (function () {
            var s = document.querySelector('script[src*="assets/js/main.js"]');
            if (!s || !s.src) return '';
            return s.src.replace(/\/assets\/js\/main\.js(?:\?.*)?$/, '');
        })();

        function initSSE() {
            if (notifEventSource) notifEventSource.close();
            if (!notifBellBtn || typeof EventSource === 'undefined') return;

            notifEventSource = new EventSource(appBase + '/api/notifications_sse.php');

            notifEventSource.onmessage = function (e) {
                try {
                    var data = JSON.parse(e.data);
                    if (data.type === 'init') {
                        updateNotifBadge(data.unread_count || 0);
                    } else if (data.type === 'ping') {
                        // Keep-alive, no action needed
                    } else {
                        addNotifItem(data);
                        // Increment badge by 1
                        var current = parseInt(notifBadge.textContent, 10) || 0;
                        updateNotifBadge(current + 1);
                    }
                } catch (err) { /* ignore malformed messages */ }
            };

            notifEventSource.onerror = function () {
                notifEventSource.close();
                setTimeout(initSSE, 5000);
            };
        }

        if (notifBellBtn) {
            notifBellBtn.addEventListener('shown.bs.dropdown', function () {
                initSSE();
            });
        }

        // Delegate mark-as-read clicks in the dropdown
        document.addEventListener('click', function (e) {
            var markLink = e.target.closest('.mark-read');
            if (markLink) {
                e.preventDefault();
                var id = markLink.getAttribute('data-id');
                fetch(appBase + '/api/notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'mark_read', id: parseInt(id, 10) })
                }).then(function (r) { return r.json(); }).then(function () {
                    updateNotifBadge(0);
                });
            }
        });
    });

    window.printSection = function (id) {
        var el = document.getElementById(id);
        if (!el) { window.print(); return; }
        var w = window.open('', '', 'width=800,height=900');
        w.document.write('<html><head><title>Print</title>');
        w.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">');
        w.document.write('<style>body{padding:24px;}</style>');
        w.document.write('</head><body>');
        w.document.write(el.innerHTML);
        w.document.write('</body></html>');
        w.document.close();
        w.focus();
        setTimeout(function () { w.print(); w.close(); }, 400);
    };
})();