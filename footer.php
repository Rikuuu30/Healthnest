<footer class="site-footer">
        <div class="footer-inner">
            <strong>HealthNest</strong>
            <p>&copy; <?php echo date("Y"); ?> Seller and buyer wellness shop portal.</p>
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
            const main = document.querySelector(".page-main");

            if (!main) {
                document.documentElement.classList.remove("seller-restoring-scroll");
                return;
            }

            const saveScroll = () => {
                sessionStorage.setItem(key, String(window.scrollY));
            };

            const restoreScroll = () => {
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

            window.addEventListener("beforeunload", saveScroll);
            window.addEventListener("pageshow", restoreScroll);
            restoreScroll();
        })();
        </script>
    <?php endif; ?>
</body>
</html>
