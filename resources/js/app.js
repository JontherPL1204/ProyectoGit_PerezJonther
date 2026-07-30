import {
    ArrowLeft,
    ArrowRight,
    BarChart3,
    CalendarDays,
    CalendarPlus,
    Check,
    CheckCircle2,
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
    Undo2,
    X,
    XCircle,
    Eye,
    FileCheck2,
    History,
    Mail,
    createIcons,
} from 'lucide';

const icons = {
    ArrowLeft,
    ArrowRight,
    BarChart3,
    CalendarDays,
    CalendarPlus,
    Check,
    CheckCircle2,
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
    Undo2,
    X,
    XCircle,
    Eye,
    FileCheck2,
    History,
    Mail,
};

document.addEventListener('DOMContentLoaded', () => {
    createIcons({ icons });

    const leaveTypeSelect = document.querySelector('[data-leave-type-select]');

    if (!leaveTypeSelect) {
        return;
    }

    const help = document.querySelector('[data-leave-type-help]');
    const helpTooltip = help?.querySelector('.tooltip');
    const guidance = document.querySelector('[data-request-guidance]');
    const medicalDurationField = document.querySelector('[data-medical-duration-field]');
    const durationInputs = document.querySelectorAll('input[name="duration_unit"]');
    const startDateLabel = document.querySelector('[data-start-date-label]');
    const startDateInput = document.querySelector('[data-start-date]');
    const endDateField = document.querySelector('[data-end-date-field]');
    const endDateInput = document.querySelector('[data-end-date]');
    const timeFields = document.querySelectorAll('[data-time-field]');

    const selectedDurationUnit = () => document.querySelector('input[name="duration_unit"]:checked')?.value ?? 'DAYS';

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

        if (startDateLabel) {
            startDateLabel.textContent = isHourly ? 'Fecha del permiso' : 'Fecha inicio';
        }

        if (endDateField && endDateInput) {
            endDateField.hidden = isHourly;
            endDateInput.required = !isHourly;
        }

        setTimeFieldsRequired(isHourly);
        syncEndDateForHourlyRequest();
    };

    leaveTypeSelect.addEventListener('change', updateRequestForm);
    durationInputs.forEach((input) => input.addEventListener('change', updateRequestForm));
    startDateInput?.addEventListener('change', syncEndDateForHourlyRequest);
    leaveTypeSelect.closest('form')?.addEventListener('submit', syncEndDateForHourlyRequest);
    updateRequestForm();
});
