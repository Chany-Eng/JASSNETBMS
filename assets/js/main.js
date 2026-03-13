// JASSNET Business Management System JavaScript

// Confirm delete actions
function confirmDelete(message = 'Are you sure you want to delete this item?') {
    return confirm(message);
}

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.display = 'none';
        }, 5000);
    });
});

// Format currency
function formatCurrency(amount) {
    return 'Tshs. ' + new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

// Validate form
function validateForm(formId) {
    const form = document.getElementById(formId);
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(function(input) {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Add page-size selectors and lightweight pagination to table views.
document.addEventListener('DOMContentLoaded', function() {
    const allowedPageSizes = [10, 25, 50, 100, 200];
    const tables = document.querySelectorAll('table.table');

    function ensureRowNumberColumn(table) {
        if (table.dataset.rowNumbersInitialized === 'true') {
            return;
        }

        const headRow = table.tHead && table.tHead.rows.length ? table.tHead.rows[0] : null;
        if (headRow) {
            const existingHead = headRow.querySelector('th.row-number-header');
            if (!existingHead) {
                const numberHead = document.createElement('th');
                numberHead.className = 'row-number-header';
                numberHead.textContent = '#';
                headRow.insertBefore(numberHead, headRow.firstChild);
            }
        }

        const tbody = table.tBodies[0];
        if (tbody) {
            Array.from(tbody.rows).forEach(function(row) {
                const firstCell = row.cells[0];
                if (firstCell && firstCell.classList.contains('row-number-cell')) {
                    return;
                }
                const numberCell = document.createElement('td');
                numberCell.className = 'row-number-cell text-muted fw-semibold';
                row.insertBefore(numberCell, row.firstChild);
            });
        }

        table.dataset.rowNumbersInitialized = 'true';
    }

    function updateRowNumbers(table, startNumber, visibleOnly) {
        const tbody = table.tBodies[0];
        if (!tbody) {
            return;
        }

        let counter = startNumber;
        Array.from(tbody.rows).forEach(function(row) {
            const numberCell = row.querySelector('td.row-number-cell');
            if (!numberCell) {
                return;
            }

            if (row.cells.length === 2 && row.cells[1] && row.cells[1].hasAttribute('colspan')) {
                numberCell.textContent = '';
                return;
            }

            const isHidden = row.style.display === 'none';
            if (visibleOnly && isHidden) {
                numberCell.textContent = '';
                return;
            }

            numberCell.textContent = counter;
            counter += 1;
        });
    }

    tables.forEach(function(table, index) {
        ensureRowNumberColumn(table);

        if (table.dataset.tablePagination === 'server') {
            const startNumber = parseInt(table.dataset.rowNumberStart || '1', 10);
            updateRowNumbers(table, Number.isNaN(startNumber) ? 1 : startNumber, false);
            return;
        }

        if (table.dataset.tablePagination === 'server' || table.dataset.tablePaginationInitialized === 'true') {
            return;
        }

        const tbody = table.tBodies[0];
        if (!tbody || !tbody.rows.length) {
            return;
        }

        const rows = Array.from(tbody.rows);
        const storageKey = 'jassnet-table-page-size:' + window.location.pathname + ':' + index;
        let currentPage = 1;
        let pageSize = parseInt(window.localStorage.getItem(storageKey) || '25', 10);

        if (!allowedPageSizes.includes(pageSize)) {
            pageSize = 25;
        }

        const wrapper = table.closest('.table-responsive') || table;
        if (!wrapper || !wrapper.parentNode) {
            return;
        }

        const controls = document.createElement('div');
        controls.className = 'table-pagination-toolbar d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3';
        controls.innerHTML = [
            '<div class="d-flex align-items-center gap-2 flex-wrap">',
            '<label class="table-pagination-label mb-0">Show</label>',
            '<select class="form-select form-select-sm table-pagination-select" style="width: auto;">',
            allowedPageSizes.map(function(size) {
                return '<option value="' + size + '">' + size + '</option>';
            }).join(''),
            '</select>',
            '<span class="table-pagination-label">entries</span>',
            '</div>',
            '<div class="d-flex align-items-center gap-2 flex-wrap justify-content-lg-end">',
            '<span class="table-pagination-info text-muted small"></span>',
            '<div class="btn-group btn-group-sm table-pagination-buttons" role="group"></div>',
            '</div>'
        ].join('');

        wrapper.parentNode.insertBefore(controls, wrapper);

        const pageSizeSelect = controls.querySelector('.table-pagination-select');
        const info = controls.querySelector('.table-pagination-info');
        const buttons = controls.querySelector('.table-pagination-buttons');

        pageSizeSelect.value = String(pageSize);

        function createButton(label, disabled, onClick, isActive) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-secondary';
            if (isActive) {
                button.classList.add('active');
            }
            button.textContent = label;
            button.disabled = disabled;
            button.addEventListener('click', onClick);
            return button;
        }

        function renderPaginationButtons(totalPages) {
            buttons.innerHTML = '';

            buttons.appendChild(createButton('First', currentPage === 1, function() {
                currentPage = 1;
                renderTable();
            }));

            buttons.appendChild(createButton('Prev', currentPage === 1, function() {
                currentPage -= 1;
                renderTable();
            }));

            buttons.appendChild(createButton(currentPage + ' / ' + totalPages, true, function() {}, true));

            buttons.appendChild(createButton('Next', currentPage === totalPages, function() {
                currentPage += 1;
                renderTable();
            }));

            buttons.appendChild(createButton('Last', currentPage === totalPages, function() {
                currentPage = totalPages;
                renderTable();
            }));
        }

        function renderTable() {
            const totalRows = rows.length;
            const totalPages = Math.max(1, Math.ceil(totalRows / pageSize));
            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = startIndex + pageSize;

            if (currentPage > totalPages) {
                currentPage = totalPages;
            }

            rows.forEach(function(row, rowIndex) {
                row.style.display = rowIndex >= startIndex && rowIndex < endIndex ? '' : 'none';
            });

            updateRowNumbers(table, startIndex + 1, true);

            const visibleStart = totalRows === 0 ? 0 : startIndex + 1;
            const visibleEnd = Math.min(endIndex, totalRows);
            info.textContent = 'Showing ' + visibleStart + ' to ' + visibleEnd + ' of ' + totalRows + ' entries';
            renderPaginationButtons(totalPages);
        }

        pageSizeSelect.addEventListener('change', function() {
            pageSize = parseInt(this.value, 10);
            currentPage = 1;
            window.localStorage.setItem(storageKey, String(pageSize));
            renderTable();
        });

        table.dataset.tablePaginationInitialized = 'true';
        renderTable();
    });
});