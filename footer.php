<footer class="site-footer">
        <div class="footer-inner">
            <strong>HealthNest</strong>
            &copy; <?php echo date("Y"); ?> Disclaimer: This website is for educational purposes only and is a requirement for our final project. 
        </div>
    </footer>
    <?php if (!empty($user) && isAdmin()): ?>
            </div>
        </div>
    <?php endif; ?>
    <?php if (!empty($user) && isAdmin()): ?>
        <script>
        (() => {
            const key = `healthnest:seller-scroll:${window.location.pathname}`;
            const forceTopKey = `healthnest:seller-force-top:${window.location.pathname}`;
            const main = document.querySelector(".page-main");

            if (!main) {
                document.documentElement.classList.remove("seller-restoring-scroll");
                return;
            }

            const saveScroll = () => {
                sessionStorage.setItem(key, String(window.scrollY));
            };

            const restoreScroll = () => {
                if (sessionStorage.getItem(forceTopKey) !== null) {
                    sessionStorage.removeItem(forceTopKey);
                    sessionStorage.removeItem(key);
                    window.scrollTo(0, 0);

                    requestAnimationFrame(() => {
                        window.scrollTo(0, 0);
                        document.documentElement.classList.remove("seller-restoring-scroll");
                    });
                    return;
                }

                const saved = sessionStorage.getItem(key);

                if (saved === null) {
                    document.documentElement.classList.remove("seller-restoring-scroll");
                    return;
                }

                const top = Math.max(0, Number(saved) || 0);
                window.scrollTo(0, top);

                requestAnimationFrame(() => {
                    window.scrollTo(0, top);
                    sessionStorage.removeItem(key);
                    document.documentElement.classList.remove("seller-restoring-scroll");
                });
            };

            main.addEventListener("pointerdown", saveScroll, true);
            main.addEventListener("click", saveScroll, true);
            main.addEventListener("change", saveScroll, true);
            main.addEventListener("submit", saveScroll, true);

            document.addEventListener("click", (event) => {
                const link = event.target.closest("a[href]");

                if (!link) {
                    return;
                }

                const targetUrl = new URL(link.href, window.location.href);

                if (targetUrl.origin !== window.location.origin || targetUrl.pathname === window.location.pathname) {
                    return;
                }

                sessionStorage.setItem(`healthnest:seller-force-top:${targetUrl.pathname}`, "1");
            }, true);

            window.addEventListener("beforeunload", saveScroll);
            window.addEventListener("pageshow", restoreScroll);
            restoreScroll();
        })();
        </script>
    <?php endif; ?>
</body>
</html>
