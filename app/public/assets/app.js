/* ---------------------------------------------------------------------------
   ONE ERP Finance & Project Control — interactions légères (sans dépendance)
   --------------------------------------------------------------------------- */
(function () {
    'use strict';

    /**
     * Impression : tout bouton [data-print] déclenche la boîte de dialogue
     * d'impression du navigateur. Le rendu papier est défini dans app.css.
     */
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-print]');
        if (!trigger) {
            return;
        }

        event.preventDefault();
        window.print();
    });

    /**
     * Collection de jalons : ajout / suppression de lignes.
     * Le prototype est fourni par Symfony (data-prototype).
     */
    function initCollections() {
        document.querySelectorAll('[data-collection]').forEach(function (container) {
            var prototype = container.dataset.prototype;
            var list = container.querySelector('[data-collection-items]');
            var addButton = container.querySelector('[data-collection-add]');
            var index = parseInt(container.dataset.index || '0', 10);

            if (!list || !prototype) {
                return;
            }

            function refreshEmpty() {
                var empty = container.querySelector('[data-collection-empty]');
                if (empty) {
                    empty.hidden = list.querySelectorAll('[data-collection-item]').length > 0;
                }
            }

            if (addButton) {
                addButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    var html = prototype.replace(/__name__/g, String(index++));
                    var wrapper = document.createElement('div');
                    wrapper.innerHTML = html.trim();
                    var item = wrapper.firstElementChild;
                    if (item) {
                        list.appendChild(item);
                        refreshEmpty();
                        var firstInput = item.querySelector('input, select, textarea');
                        if (firstInput) {
                            firstInput.focus();
                        }
                    }
                });
            }

            container.addEventListener('click', function (event) {
                var remove = event.target.closest('[data-collection-remove]');
                if (!remove) {
                    return;
                }
                event.preventDefault();
                var item = remove.closest('[data-collection-item]');
                if (item) {
                    item.remove();
                    refreshEmpty();
                }
            });

            refreshEmpty();
        });
    }

    /** Confirmation des actions destructrices (clôture, annulation…). */
    function initConfirmations() {
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm(form.dataset.confirm)) {
                    event.preventDefault();
                }
            });
        });
    }

    /** Saisie monétaire : normalisation à la sortie du champ. */
    function initMoneyInputs() {
        document.querySelectorAll('input[data-money]').forEach(function (input) {
            input.addEventListener('blur', function () {
                var value = input.value.trim().replace(/\s|\u00a0|\u202f/g, '').replace(',', '.');
                var parsed = parseFloat(value);
                if (!isNaN(parsed)) {
                    input.value = parsed.toFixed(3);
                }
            });
        });
    }

    /** Soumission automatique des filtres (selects). */
    function initAutoFilters() {
        document.querySelectorAll('[data-auto-submit]').forEach(function (element) {
            element.addEventListener('change', function () {
                var form = element.closest('form');
                if (form) {
                    form.submit();
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initCollections();
        initConfirmations();
        initMoneyInputs();
        initAutoFilters();
    });
})();
