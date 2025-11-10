(function ($) {
    $(document).ready(function () {
        const resourcesCheckboxes = $("input[type='checkbox'][name='resource_ids[]']");
        const exportButtonForm = $('#export-button-form');
        const selectedIds = new Set();

        resourcesCheckboxes.on('change', function () {
            const value = $(this).val();

            if ($(this).is(':checked')) {
                selectedIds.add(value);
            } else {
                selectedIds.delete(value);
            }

            exportButtonForm.find('input[name="resource_ids[]"]').remove();
            selectedIds.forEach(function(id) {
                $('<input>').attr({
                    type: 'hidden',
                    name: 'resource_ids[]',
                    value: id
                }).appendTo(exportButtonForm);
            });
        });
    });
})(jQuery);