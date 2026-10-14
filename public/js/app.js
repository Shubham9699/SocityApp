(function ($) {
    'use strict';

    if (!$) {
        return;
    }

    $(function () {
        var themeKey = 'lotus-orchid-theme';
        var $themeToggle = $('[data-theme-toggle]');

        function applyTheme(theme) {
            var isDark = theme === 'dark';
            document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
            $themeToggle.attr('aria-pressed', String(isDark));
            $themeToggle.attr('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
            $themeToggle.find('[data-theme-label]').text(isDark ? 'Light mode' : 'Dark mode');
        }

        applyTheme(document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light');
        $themeToggle.on('click', function () {
            var nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
            localStorage.setItem(themeKey, nextTheme);
            applyTheme(nextTheme);
        });

        var $sidebar = $('#sidebar');
        var $scrim = $('#sidebarScrim');

        function closeSidebar() {
            $sidebar.removeClass('is-open');
            $scrim.removeClass('is-visible');
        }

        $('#menuToggle').on('click', function () {
            $sidebar.toggleClass('is-open');
            $scrim.toggleClass('is-visible');
        });
        $scrim.on('click', closeSidebar);
        $(document).on('keydown', function (event) {
            if (event.key === 'Escape') closeSidebar();
        });

        $('.flash-close').on('click', function () {
            $(this).closest('.flash').fadeOut(150);
        });

        $('.demo-login').on('click', function () {
            $('#email').val($(this).data('email'));
            $('#password').val('Secret@123');
            $('#role').val($(this).data('role'));
            $(this).closest('.auth-card').find('form[action$="/login"]').trigger('submit');
        });

        $('#noticeSearch, #noticeCategory').on('input change', function () {
            var query = ($('#noticeSearch').val() || '').toLowerCase().trim();
            var category = $('#noticeCategory').val() || '';
            var visible = 0;

            $('.notice-card').each(function () {
                var $card = $(this);
                var matches = (!category || $card.data('category') === category)
                    && (!query || String($card.data('search')).indexOf(query) !== -1);
                $card.toggle(matches);
                if (matches) visible++;
            });

            $('#noticeEmpty').prop('hidden', visible !== 0);
        });

        $('#directorySearch').on('input', function () {
            var query = ($(this).val() || '').toLowerCase().trim();
            var visible = 0;

            $('#directoryTable tbody tr').each(function () {
                var matches = String($(this).data('search')).indexOf(query) !== -1;
                $(this).toggle(matches);
                if (matches) visible++;
            });

            $('#directoryEmpty').prop('hidden', visible !== 0);
        });

        $('#bill_period').on('change', function () {
            var parts = ($(this).val() || '').split('-');
            if (parts.length !== 2) return;
            var dueMonth = parts[1] === '12' ? '01' : String(Number(parts[1]) + 1).padStart(2, '0');
            var dueYear = parts[1] === '12' ? String(Number(parts[0]) + 1) : parts[0];
            $('#due_date').val(dueYear + '-' + dueMonth + '-15');
        });
    });
}(window.jQuery));
