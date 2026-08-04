import {
    ArrowLeft,
    ArrowRight,
    BarChart3,
    Briefcase,
    CalendarDays,
    CalendarPlus,
    ChevronDown,
    Check,
    CheckCircle2,
    Copy,
    Download,
    Inbox,
    Info,
    LayoutDashboard,
    LogIn,
    LogOut,
    Plus,
    PlusCircle,
    RotateCcw,
    Save,
    Search,
    Send,
    SlidersHorizontal,
    Trash2,
    Undo2,
    UserPlus,
    Users,
    Wrench,
    X,
    XCircle,
    Eye,
    FileCheck2,
    History,
    Mail,
    createIcons,
} from 'lucide';
import flatpickr from 'flatpickr';
import { Spanish } from 'flatpickr/dist/l10n/es.js';
import 'flatpickr/dist/flatpickr.min.css';

const icons = {
    ArrowLeft,
    ArrowRight,
    BarChart3,
    Briefcase,
    CalendarDays,
    CalendarPlus,
    ChevronDown,
    Check,
    CheckCircle2,
    Copy,
    Download,
    Inbox,
    Info,
    LayoutDashboard,
    LogIn,
    LogOut,
    Plus,
    PlusCircle,
    RotateCcw,
    Save,
    Search,
    Send,
    SlidersHorizontal,
    Trash2,
    Undo2,
    UserPlus,
    Users,
    Wrench,
    X,
    XCircle,
    Eye,
    FileCheck2,
    History,
    Mail,
};

document.addEventListener('DOMContentLoaded', () => {
    createIcons({ icons });

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const detectedTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

    document.querySelectorAll('[data-detected-timezone]').forEach((input) => {
        if (detectedTimezone && !input.value) {
            input.value = detectedTimezone;
        }
    });

    const timezoneSync = document.querySelector('[data-timezone-sync-url]');

    if (timezoneSync && csrfToken && detectedTimezone && timezoneSync.dataset.currentTimezone !== detectedTimezone) {
        fetch(timezoneSync.dataset.timezoneSyncUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: JSON.stringify({ timezone: detectedTimezone }),
        }).catch(() => {});
    }

    document.querySelectorAll('[data-copy-button]').forEach((button) => {
        button.addEventListener('click', async () => {
            const input = button.closest('.copy-box')?.querySelector('[data-copy-source]');

            if (!input) {
                return;
            }

            try {
                await navigator.clipboard.writeText(input.value);
            } catch {
                input.select();
                document.execCommand('copy');
            }

            button.querySelector('span').textContent = 'Enlace copiado';
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-status-select]').forEach((select) => {
        const syncStatusStyle = () => {
            select.classList.toggle('status-approved', select.value === '1');
            select.classList.toggle('status-cancelled', select.value !== '1');
        };

        select.addEventListener('change', syncStatusStyle);
        syncStatusStyle();
    });

    document.querySelectorAll('[data-role-permissions]').forEach((form) => {
        const roleSelect = form.querySelector('[data-role-select]');
        const adminOnlyInputs = form.querySelectorAll('[data-admin-only-permission]');

        const syncRolePermissions = () => {
            const isAdmin = roleSelect?.value === 'admin';

            adminOnlyInputs.forEach((input) => {
                const isLocked = input.dataset.locked === '1';
                input.disabled = isLocked || !isAdmin;

                if (!isAdmin) {
                    input.checked = false;
                }
            });
        };

        roleSelect?.addEventListener('change', syncRolePermissions);
        syncRolePermissions();
    });

    const leaveTypeSelect = document.querySelector('[data-leave-type-select]');

    if (!leaveTypeSelect) {
        return;
    }

    const help = document.querySelector('[data-leave-type-help]');
    const helpTooltip = help?.querySelector('.tooltip');
    const guidance = document.querySelector('[data-request-guidance]');
    const medicalDurationField = document.querySelector('[data-medical-duration-field]');
    const durationInputs = document.querySelectorAll('input[name="duration_unit"]');
    const dateRangeLabel = document.querySelector('[data-date-range-label]');
    const dateRangeDisplay = document.querySelector('[data-date-range-display]');
    const startDateInput = document.querySelector('[data-start-date]');
    const endDateInput = document.querySelector('[data-end-date]');
    const timeFields = document.querySelectorAll('[data-time-field]');
    let datePicker;

    const selectedDurationUnit = () => document.querySelector('input[name="duration_unit"]:checked')?.value ?? 'DAYS';
    const selectedRequestMode = () => {
        const option = leaveTypeSelect.selectedOptions[0];
        const isMedical = option?.dataset.isMedical === '1';
        const baseUnit = option?.dataset.unit ?? 'DAYS';

        return isMedical ? selectedDurationUnit() : baseUnit;
    };

    const setTimeFieldsRequired = (required) => {
        timeFields.forEach((field) => {
            field.hidden = !required;
            field.querySelectorAll('input').forEach((input) => {
                input.required = required;
                if (!required) {
                    input.value = '';
                }
            });
        });
    };

    const syncEndDateForHourlyRequest = () => {
        if (startDateInput && endDateInput && selectedDurationUnit() === 'MINUTES') {
            endDateInput.value = startDateInput.value;
        }
    };

    const initDatePicker = () => {
        if (!dateRangeDisplay || !startDateInput || !endDateInput) {
            return;
        }

        const isHourly = selectedRequestMode() === 'MINUTES';
        const defaultDates = [startDateInput.value, isHourly ? null : endDateInput.value].filter(Boolean);

        datePicker?.destroy();
        dateRangeDisplay.value = '';

        datePicker = flatpickr(dateRangeDisplay, {
            mode: isHourly ? 'single' : 'range',
            locale: Spanish,
            dateFormat: 'd/m/Y',
            defaultDate: defaultDates,
            allowInput: false,
            clickOpens: true,
            onChange: (selectedDates, _dateStr, instance) => {
                dateRangeDisplay.setCustomValidity('');

                if (!selectedDates[0]) {
                    startDateInput.value = '';
                    endDateInput.value = '';
                    return;
                }

                startDateInput.value = instance.formatDate(selectedDates[0], 'Y-m-d');

                if (isHourly) {
                    endDateInput.value = startDateInput.value;
                    instance.close();
                    return;
                }

                endDateInput.value = selectedDates[1] ? instance.formatDate(selectedDates[1], 'Y-m-d') : '';

                if (selectedDates[1]) {
                    instance.close();
                }
            },
        });
    };

    const updateRequestForm = () => {
        const option = leaveTypeSelect.selectedOptions[0];
        const isMedical = option?.dataset.isMedical === '1';
        const baseUnit = option?.dataset.unit ?? 'DAYS';
        const durationUnit = isMedical ? selectedDurationUnit() : baseUnit;
        const helpText = option?.dataset.help ?? 'Este motivo requiere revision de la persona responsable.';

        if (help) {
            help.title = helpText;
        }

        if (helpTooltip) {
            helpTooltip.textContent = helpText;
        }

        if (guidance) {
            guidance.textContent = helpText;
        }

        if (medicalDurationField) {
            medicalDurationField.hidden = !isMedical;
        }

        const isHourly = durationUnit === 'MINUTES';

        if (dateRangeLabel) {
            dateRangeLabel.textContent = isHourly ? 'Fecha del permiso' : 'Rango de fechas';
        }

        setTimeFieldsRequired(isHourly);
        syncEndDateForHourlyRequest();
        initDatePicker();
    };

    leaveTypeSelect.addEventListener('change', updateRequestForm);
    durationInputs.forEach((input) => input.addEventListener('change', updateRequestForm));
    startDateInput?.addEventListener('change', syncEndDateForHourlyRequest);
    leaveTypeSelect.closest('form')?.addEventListener('submit', (event) => {
        syncEndDateForHourlyRequest();

        const needsEndDate = selectedRequestMode() !== 'MINUTES';
        const isComplete = startDateInput?.value && (!needsEndDate || endDateInput?.value);

        if (!isComplete) {
            event.preventDefault();
            dateRangeDisplay?.setCustomValidity(needsEndDate ? 'Selecciona fecha de inicio y fecha final.' : 'Selecciona la fecha del permiso.');
            dateRangeDisplay?.reportValidity();
        }
    });
    updateRequestForm();
});
