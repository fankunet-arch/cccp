// 子菜单展开/收起 + 移动端侧栏开关
$(function() {
    // 子菜单
    $('.main-sidebar .treeview .menu-toggle').on('click', function(e) {
        e.preventDefault();
        const $ul = $(this).next('.treeview-menu');
        $ul.slideToggle(150);
        $(this).closest('.treeview').toggleClass('menu-open');
    });

    // 移动端侧栏开关
    var $body = $('body');
    $('.sidebar-toggle-btn').on('click', function(){
        $body.toggleClass('sidebar-open');
    });
    $('.sidebar-backdrop').on('click', function(){
        $body.removeClass('sidebar-open');
    });
    $(document).on('keydown', function(e){
        if (e.key === 'Escape') $body.removeClass('sidebar-open');
    });
});