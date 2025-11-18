$(document).ready(function() {
    const form = $('#subject-form');
    const formTitle = $('#form-title');
    const submitText = $('#submit-text');
    const cancelBtn = $('#cancel-btn');
    const feedbackContainer = $('#feedback-container');

    // 编辑按钮点击
    $('.edit-btn').on('click', function() {
        const subjectId = $(this).data('id');
        loadSubjectData(subjectId);
    });

    // 取消按钮
    cancelBtn.on('click', function() {
        resetForm();
    });

    // 加载主体数据
    function loadSubjectData(id) {
        $.ajax({
            url: '/index.php?action=dts_subject_get_data&id=' + id,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    populateForm(response.data);

                    // 滚动到表单
                    $('html, body').animate({
                        scrollTop: form.offset().top - 100
                    }, 500);
                } else {
                    alert('加载数据失败：' + (response.message || '未知错误'));
                }
            },
            error: function() {
                alert('加载数据时发生客户端错误');
            }
        });
    }

    // 填充表单
    function populateForm(data) {
        $('#subject_id').val(data.id);
        $('#subject_name').val(data.subject_name);
        $('#subject_type').val(data.subject_type);
        $('#subject_status').val(data.subject_status);
        $('#remark').val(data.remark || '');

        formTitle.html('<i class="fas fa-edit"></i> 编辑主体');
        submitText.text('更新');
        cancelBtn.show();
    }

    // 重置表单
    function resetForm() {
        form[0].reset();
        $('#subject_id').val('');
        formTitle.html('<i class="fas fa-plus-circle"></i> 新增主体');
        submitText.text('保存');
        cancelBtn.hide();
    }

    // Toast 提示
    if ($('#feedback-bar').length) {
        setTimeout(function() {
            $('#feedback-bar').fadeOut();
        }, 3000);
    }
});