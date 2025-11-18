$(document).ready(function() {
    // 大类改变时，更新小类
    $('#object_type_main').on('change', function() {
        updateSubCategories($(this).val(), '');
    });

    // 页面加载时，如果有已选择的大类，更新小类
    const selectedMainCat = $('#object_type_main').val();
    if (selectedMainCat) {
        updateSubCategories(selectedMainCat, currentSubCat);
    }

    // 更新小类下拉选项
    function updateSubCategories(mainCat, selectedSubCat) {
        const subCatSelect = $('#object_type_sub');
        subCatSelect.empty();

        if (!mainCat || !categories[mainCat]) {
            subCatSelect.append('<option value="">请先选择大类</option>');
            subCatSelect.prop('disabled', true);
            return;
        }

        const subCats = categories[mainCat];

        // 始终添加"不选择小类"选项
        subCatSelect.append('<option value="">（不选择小类）</option>');

        if (subCats.length === 0) {
            // 如果该大类没有小类，禁用下拉但保留"不选择小类"选项
            subCatSelect.prop('disabled', true);
            return;
        }

        // 启用下拉并添加所有小类选项
        subCatSelect.prop('disabled', false);

        subCats.forEach(function(subCat) {
            const option = $('<option></option>')
                .val(subCat)
                .text(subCat);

            if (subCat === selectedSubCat) {
                option.attr('selected', 'selected');
            }

            subCatSelect.append(option);
        });
    }
});