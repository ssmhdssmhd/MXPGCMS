/* =========================================================
   MXMB Search - 搜索页渲染
   Author: 射手沫蝴蝶(MX) | MXPGCMS
   ========================================================= */
(function () {
    'use strict';

    function $(s, ctx) { return (ctx || document).querySelector(s); }
    function esc(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function curPageFromUrl() {
        var p = new URLSearchParams(window.location.search).get('page');
        p = parseInt(p, 10);
        return Number.isFinite(p) && p > 0 ? p : 1;
    }
    function linkWithPage(page) {
        var u = new URL(window.location.href);
        u.searchParams.set('page', String(page));
        return u.pathname + u.search;
    }

    function card(item) {
        var pic = item.pic || '';
        var name = item.name || '';
        var tagText = (item.class || item.module_name || '影视').split(/[\s,，/|]+/)[0] || '影视';
        var score = item.score;
        var remarks = item.remarks;
        return '<div class="mx-vitem">' +
            '<a class="mx-cover" href="' + esc(item.link) + '" title="' + esc(name) + '">' +
            '<img src="' + esc(pic) + '" alt="' + esc(name) + '" loading="lazy" />' +
            '<span class="mx-tag">' + esc(tagText) + '</span>' +
            (score ? '<span class="mx-score">' + esc(score) + '</span>' : '') +
            (remarks ? '<span class="mx-remarks">' + esc(remarks) + '</span>' : '') +
            '</a>' +
            '<p class="mx-name"><a href="' + esc(item.link) + '" title="' + esc(name) + '">' + esc(name) + '</a></p>' +
            '<p class="mx-sub">' + esc((item.actor || '').slice(0, 14)) + '</p>' +
            '</div>';
    }

    function renderPager(container, page, total) {
        var size = 12, pages = Math.max(1, Math.ceil(total / size));
        if (pages <= 1) { container.innerHTML = ''; return; }
        var html = '';
        var first = Math.max(1, page - 2), last = Math.min(pages, page + 2);
        var prev = page > 1 ? '<a href="' + linkWithPage(page - 1) + '">上一页</a>' : '<a class="disabled">上一页</a>';
        var next = page < pages ? '<a href="' + linkWithPage(page + 1) + '">下一页</a>' : '<a class="disabled">下一页</a>';
        html += '<a href="' + linkWithPage(1) + '">首页</a>' + prev;
        for (var i = first; i <= last; i++) {
            html += i === page
                ? '<span class="active">' + i + '</span>'
                : '<a href="' + linkWithPage(i) + '">' + i + '</a>';
        }
        html += next + '<a href="' + linkWithPage(pages) + '">尾页</a>';
        container.innerHTML = html;
    }

    function load() {
        var pageEl = $('#mxSearchPage');
        if (!pageEl) return;
        var url = new URL(window.location.href);
        var q = url.searchParams.get('wd') || pageEl.getAttribute('data-search-wd') || '';
        var by = (url.searchParams.get('by') || pageEl.getAttribute('data-search-by') || 'time');
        var page = curPageFromUrl();
        var apiBase = pageEl.getAttribute('data-api-base') || (window.maccms && window.maccms.path ? window.maccms.path.replace(/\/+$/, '') + '/api.php' : '/api.php');

        $('.mx-search-wd') && ($('.mx-search-wd').textContent = q);
        // 排序高亮
        $('.mx-search-sort').forEach && document.querySelectorAll('.mx-search-sort').forEach(function (a) {
            a.classList.toggle('active', a.getAttribute('data-by') === by);
        });
        sortButtons();

        if (!q) { showEmpty(); return; }

        var api = apiBase.replace(/\/+$/, '') + '/search/index?module=all&limit=12&page=' + page + '&wd=' + encodeURIComponent(q);
        fetch(api, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || data.code !== 1 || !data.info) { showEmpty(); return; }
                var total = 0, list = [];
                ['vod', 'art', 'manga'].forEach(function (mod) {
                    var sec = data.info[mod] || {};
                    total += parseInt(sec.total, 10) || 0;
                    list = list.concat(Array.isArray(sec.list) ? sec.list : []);
                });
                // 排序
                if (by === 'hits') list.sort(function (a, b) { return (parseInt(b.hits, 10) || 0) - (parseInt(a.hits, 10) || 0); });
                else if (by === 'score') list.sort(function (a, b) { return (parseFloat(b.score) || 0) - (parseFloat(a.score) || 0); });
                else list.sort(function (a, b) { return (parseInt(b.time, 10) || 0) - (parseInt(a.time, 10) || 0); });

                $('.mx-search-total') && ($('.mx-search-total').textContent = total);
                var holder = $('.mx-search-list');
                holder.innerHTML = total > 0 ? list.map(card).join('') : '';
                $('.mx-search-empty') && ($('.mx-search-empty').style.display = total > 0 ? 'none' : 'block');
                renderPager($('#mxSearchPager'), page, total);
            })
            .catch(function () { showEmpty(); });
    }

    function showEmpty() {
        $('.mx-search-total') && ($('.mx-search-total').textContent = 0);
        $('.mx-search-list') && ($('.mx-search-list').innerHTML = '');
        $('.mx-search-empty') && ($('.mx-search-empty').style.display = 'block');
        var pager = $('#mxSearchPager');
        pager && (pager.innerHTML = '');
    }

    function sortButtons() {
        document.querySelectorAll('.mx-search-sort').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                var by = a.getAttribute('data-by');
                var u = new URL(window.location.href);
                u.searchParams.set('by', by);
                u.searchParams.set('page', '1');
                window.location.href = u.pathname + u.search;
            });
        });
    }

    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', load)
        : load();
})();