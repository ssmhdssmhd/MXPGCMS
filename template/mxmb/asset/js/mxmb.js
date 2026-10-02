/* MXMB Template - 前端基础交互 */
(function () {
    // 移动端菜单开关
    function toggleMenu() {
        var nav = document.querySelector('.mx-nav');
        var btn = document.querySelector('.mx-burger');
        if (!nav) return;
        if (btn) {
            btn.addEventListener('click', function () {
                nav.classList.toggle('mx-open');
            });
        }
        document.addEventListener('click', function (e) {
            if (!nav.contains(e.target) && btn && !btn.contains(e.target)) {
                nav.classList.remove('mx-open');
            }
        });
    }
    toggleMenu();

    // 顶部导航高亮当前
    function highlightNav() {
        var path = window.location.pathname;
        var links = document.querySelectorAll('.mx-nav a');
        links.forEach(function (a) {
            var href = a.getAttribute('href') || '';
            if (href && path.indexOf(href.replace(/^\//, '')) >= 0) {
                a.classList.add('mx-active');
            }
        });
    }
    highlightNav();
})();