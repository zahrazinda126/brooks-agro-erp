<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-directive-status]').forEach((status) => {
        const form = status.closest('form');
        const field = form?.querySelector('[data-directive-reason]');
        const input = field?.querySelector('textarea');
        const label = field?.querySelector('label');
        if (!field || !input || !label) return;

        const updateReason = () => {
            const pending = status.value === 'pending';
            const rescheduled = status.value === 'rescheduled';
            const blocked = status.value === 'blocked';
            field.hidden = !pending && !rescheduled && !blocked;
            input.disabled = field.hidden;
            input.required = !field.hidden;
            label.textContent = pending
                ? 'Reason why it is pending'
                : (blocked ? 'Reason for blocking' : 'Reason for rescheduling');
            input.placeholder = pending
                ? 'Explain why work has not started or finished'
                : (blocked
                    ? 'Explain what is blocking progress and what is needed to continue'
                    : 'Explain why the agreed date or time must change');
        };

        status.addEventListener('change', updateReason);
        updateReason();
    });
});
</script>
