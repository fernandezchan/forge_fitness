document.addEventListener("DOMContentLoaded", function () {
    var toggle = document.querySelector(".menu-toggle");
    var nav = document.querySelector(".site-nav");

    if (toggle && nav) {
        toggle.addEventListener("click", function () {
            nav.classList.toggle("open");
            toggle.classList.toggle("open");
        });

        nav.querySelectorAll("a").forEach(function (link) {
            link.addEventListener("click", function () {
                nav.classList.remove("open");
                toggle.classList.remove("open");
            });
        });
    }

    var navBar = document.querySelector(".site-nav");
    var pinnedNav = null;
    var pinTimer = null;
    var applyScrollSpy = function () {};

    function navTextLinks() {
        if (!navBar) {
            return [];
        }
        return navBar.querySelectorAll("a[data-nav]");
    }

    function setActiveNavLink(link) {
        navTextLinks().forEach(function (item) {
            item.classList.remove("active");
        });
        if (link) {
            link.classList.add("active");
        }
    }

    function pageName() {
        var parts = location.pathname.replace(/\/+$/, "").split("/");
        return parts[parts.length - 1] || "";
    }

    function isIndexPath(pathname) {
        var path = (pathname || "").replace(/\/+$/, "");
        var name = path.split("/").pop() || "";
        return name === "" || name === "index.php" || name === "webbb";
    }

    function isHomePage() {
        return isIndexPath(location.pathname);
    }

    function resolvedUrl(href) {
        try {
            return new URL(href, location.href);
        } catch (error) {
            return null;
        }
    }

    function pinActiveNav(key) {
        pinnedNav = key;
        if (navBar) {
            navBar.classList.add("is-scrolling");
            var link = navBar.querySelector('a[data-nav="' + key + '"]');
            setActiveNavLink(link);
        }
        clearTimeout(pinTimer);
        function releasePin() {
            pinnedNav = null;
            if (navBar) {
                navBar.classList.remove("is-scrolling");
            }
            window.removeEventListener("scrollend", releasePin);
            applyScrollSpy();
        }
        window.addEventListener("scrollend", releasePin, { once: true });
        pinTimer = setTimeout(releasePin, 1100);
    }

    function scrollToId(id, smooth) {
        var target = document.getElementById(id);
        if (!target) {
            return false;
        }
        target.scrollIntoView({
            behavior: smooth ? "smooth" : "auto",
            block: "start"
        });
        return true;
    }

    function samePageSectionId(href) {
        var resolved = resolvedUrl(href);
        if (!resolved) {
            return "";
        }
        var id = (resolved.hash || "").replace("#", "");
        if (!id || !document.getElementById(id) || resolved.origin !== location.origin) {
            return "";
        }
        var here = location.pathname.replace(/\/+$/, "");
        var targetPath = resolved.pathname.replace(/\/+$/, "");
        if ((targetPath === here || (isIndexPath(targetPath) && isIndexPath(here))) && resolved.search === location.search) {
            return id;
        }
        return "";
    }

    function jumpToHashInstant() {
        var hash = (window.location.hash || "").replace("#", "").toLowerCase();
        if (!hash) {
            return;
        }
        scrollToId(hash, false);
        if (navBar) {
            var match = navBar.querySelector('a[data-nav="' + hash + '"]');
            if (match) {
                setActiveNavLink(match);
            }
        }
    }

    document.addEventListener("click", function (event) {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        var link = event.target.closest("a[href]");
        if (!link || (link.target && link.target !== "_self") || link.classList.contains("admin-clear")) {
            return;
        }

        var href = link.getAttribute("href") || "";
        var navKey = link.getAttribute("data-nav");
        var resolved = resolvedUrl(href);
        var resolvedPath = resolved ? resolved.pathname.replace(/\/+$/, "") : "";
        var resolvedName = resolvedPath.split("/").pop() || "";

        if (navKey === "home" && isHomePage() && resolved && !resolved.hash && resolvedName === "index.php") {
            var home = document.getElementById("home");
            if (home) {
                event.preventDefault();
                if (location.hash) {
                    history.pushState(null, "", location.pathname + location.search);
                }
                pinActiveNav("home");
                scrollToId("home", true);
            }
            return;
        }

        var sectionId = samePageSectionId(href);
        if (!sectionId) {
            if (navKey && navBar && navBar.contains(link)) {
                setActiveNavLink(link);
            }
            return;
        }

        event.preventDefault();
        history.pushState(null, "", location.pathname + location.search + "#" + sectionId);
        pinActiveNav(sectionId);
        scrollToId(sectionId, true);
    });

    if (navBar) {
        jumpToHashInstant();
        window.addEventListener("hashchange", jumpToHashInstant);
        initScrollSpy();
    }

    function initScrollSpy() {
        var spySections = [
            { id: "home", nav: "home" },
            { id: "about", nav: "about" },
            { id: "membership", nav: "membership" },
            { id: "contact", nav: "contact" }
        ].filter(function (item) {
            return document.getElementById(item.id);
        });

        if (spySections.length < 2) {
            return;
        }

        var ticking = false;

        function sectionFromScroll() {
            var current = spySections[0].nav;
            var probe = 120;
            var nearBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 24;

            spySections.forEach(function (item) {
                var el = document.getElementById(item.id);
                if (el && el.getBoundingClientRect().top <= probe) {
                    current = item.nav;
                }
            });

            if (nearBottom) {
                current = spySections[spySections.length - 1].nav;
            }

            return current;
        }

        function updateSpy() {
            ticking = false;
            if (pinnedNav) {
                var pinnedLink = navBar.querySelector('a[data-nav="' + pinnedNav + '"]');
                if (pinnedLink) {
                    setActiveNavLink(pinnedLink);
                }
                return;
            }
            var current = sectionFromScroll();
            var link = navBar.querySelector('a[data-nav="' + current + '"]');
            if (link) {
                setActiveNavLink(link);
            }
        }

        applyScrollSpy = updateSpy;

        function onScroll() {
            if (!ticking) {
                ticking = true;
                window.requestAnimationFrame(updateSpy);
            }
        }

        window.addEventListener("scroll", onScroll, { passive: true });
        document.addEventListener("scroll", onScroll, { passive: true });

        if ("IntersectionObserver" in window) {
            var observer = new IntersectionObserver(function () {
                if (!pinnedNav) {
                    updateSpy();
                }
            }, {
                root: null,
                rootMargin: "-90px 0px -40% 0px",
                threshold: [0, 0.2, 0.4, 0.6, 0.8, 1]
            });

            spySections.forEach(function (item) {
                observer.observe(document.getElementById(item.id));
            });
        }

        updateSpy();
    }

    function fieldMessage(input) {
        var value = (input.value || "").trim();
        var matchName = input.getAttribute("data-match");

        if (input.hasAttribute("required") && value === "") {
            return "This field is required.";
        }

        if (input.type === "email" && value !== "") {
            var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(value)) {
                return "Please enter a valid email address.";
            }
        }

        var matchEmail = input.getAttribute("data-match-email");
        if (matchEmail && value !== "" && value.toLowerCase() !== matchEmail.toLowerCase()) {
            return "This email must match your logged-in account.";
        }

        if (input.minLength > 0 && value.length < input.minLength) {
            return "Please enter at least " + input.minLength + " characters.";
        }

        if (matchName) {
            var form = input.closest("form");
            var original = form ? form.querySelector('[name="' + matchName + '"]') : null;
            if (original && original.value !== input.value) {
                return "Passwords do not match.";
            }
        }

        return "";
    }

    function showError(input, message) {
        input.classList.add("is-invalid");
        input.setCustomValidity(message);

        var existing = input.parentNode.querySelector('.field-error[data-for="' + input.name + '"]');
        if (!existing) {
            existing = document.createElement("div");
            existing.className = "field-error";
            existing.setAttribute("data-for", input.name);
            input.insertAdjacentElement("afterend", existing);
        }
        existing.textContent = message;
    }

    function clearError(input) {
        input.classList.remove("is-invalid");
        input.setCustomValidity("");
        var existing = input.parentNode.querySelector('.field-error[data-for="' + input.name + '"]');
        if (existing) {
            existing.remove();
        }
    }

    document.querySelectorAll("form.js-validate").forEach(function (form) {
        var fields = form.querySelectorAll("input, select, textarea");

        fields.forEach(function (field) {
            field.addEventListener("input", function () {
                var message = fieldMessage(field);
                if (message) {
                    showError(field, message);
                } else {
                    clearError(field);
                }
            });
        });

        form.addEventListener("submit", function (event) {
            var valid = true;

            fields.forEach(function (field) {
                var message = fieldMessage(field);
                if (message) {
                    showError(field, message);
                    valid = false;
                } else {
                    clearError(field);
                }
            });

            if (!valid) {
                event.preventDefault();
                var firstInvalid = form.querySelector(".is-invalid");
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
        });
    });

    var methodSelect = document.getElementById("payment_method");
    if (methodSelect) {
        var payForm = methodSelect.closest("form");
        var payButton = payForm ? payForm.querySelector("button[type='submit']") : null;
        function syncPayButton() {
            if (!payButton) {
                return;
            }
            payButton.textContent = methodSelect.value === "Cash" ? "PAY AT GYM" : "COMPLETE PAYMENT";
        }
        methodSelect.addEventListener("change", syncPayButton);
        syncPayButton();
    }

    var adminStayPin = "forgeAdminStay";

    function pinAdminStay(section) {
        if (!section) {
            return;
        }
        try {
            sessionStorage.setItem(adminStayPin, section);
        } catch (error) {
            return;
        }
    }

    var confirmModal = document.getElementById("admin-confirm-modal");
    var confirmTitle = document.getElementById("admin-confirm-title");
    var confirmText = document.getElementById("admin-confirm-text");
    var confirmOk = document.getElementById("admin-confirm-ok");
    var pendingConfirmForm = null;

    function closeAdminConfirm() {
        pendingConfirmForm = null;
        if (confirmModal) {
            confirmModal.hidden = true;
        }
        document.body.classList.remove("admin-modal-open");
        if (confirmOk) {
            confirmOk.classList.remove("is-danger");
        }
    }

    function confirmFormMessage(form) {
        var message = (form.getAttribute("data-message") || "").trim();
        if (message) {
            return message;
        }
        var member = (form.getAttribute("data-member") || "").trim();
        var plan = (form.getAttribute("data-plan") || "").trim();
        if (member && plan) {
            return "Confirm cash payment for " + member + "'s " + plan + " plan? This starts a 30-day membership.";
        }
        return "Are you sure?";
    }

    function openAdminConfirm(form) {
        pendingConfirmForm = form;
        if (confirmTitle) {
            confirmTitle.textContent = form.getAttribute("data-title") || "CONFIRM";
        }
        if (confirmText) {
            confirmText.textContent = confirmFormMessage(form);
        }
        if (confirmOk) {
            confirmOk.textContent = form.getAttribute("data-ok") || "CONFIRM";
            if (form.getAttribute("data-danger") === "1") {
                confirmOk.classList.add("is-danger");
            } else {
                confirmOk.classList.remove("is-danger");
            }
        }
        if (confirmModal) {
            confirmModal.hidden = false;
            document.body.classList.add("admin-modal-open");
        }
        if (confirmOk) {
            confirmOk.focus();
        }
    }

    document.querySelectorAll("form.js-admin-confirm").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            event.preventDefault();
            openAdminConfirm(form);
        });
    });

    if (confirmModal) {
        confirmModal.querySelectorAll("[data-modal-close]").forEach(function (el) {
            el.addEventListener("click", closeAdminConfirm);
        });
    }
    if (confirmOk) {
        confirmOk.addEventListener("click", function () {
            if (!pendingConfirmForm) {
                closeAdminConfirm();
                return;
            }
            var form = pendingConfirmForm;
            var stay = form.getAttribute("data-stay") || "";
            closeAdminConfirm();
            pinAdminStay(stay);
            form.submit();
        });
    }
    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && confirmModal && !confirmModal.hidden) {
            closeAdminConfirm();
        }
    });

    var adminToolbar = document.querySelector(".admin-toolbar");
    if (adminToolbar) {
        function jumpToAdminStay(section) {
            var target = document.getElementById(section);
            if (target) {
                target.scrollIntoView({ behavior: "auto", block: "start" });
            }
        }

        function stayFromQuery() {
            try {
                return new URLSearchParams(location.search).get("stay") || "";
            } catch (error) {
                return "";
            }
        }

        adminToolbar.addEventListener("submit", function () {
            pinAdminStay("records");
        });
        var clearLink = adminToolbar.querySelector(".admin-clear");
        if (clearLink) {
            clearLink.addEventListener("click", function () {
                pinAdminStay("records");
            });
        }

        var grantForm = document.querySelector(".admin-grant-form");
        if (grantForm) {
            grantForm.addEventListener("submit", function (event) {
                var select = document.getElementById("grant_user_id");
                var methodSelect = document.getElementById("grant_payment_method");
                var option = select && select.options[select.selectedIndex];
                var method = methodSelect ? methodSelect.value : "Cash";
                if (method !== "Cash" && option && option.getAttribute("data-active") === "1") {
                    if (!window.confirm("This member already has an active plan. Granting a new one will replace it with a new 30-day plan. Continue?")) {
                        event.preventDefault();
                        return;
                    }
                }
                pinAdminStay("memberships");
            });
        }

        document.querySelectorAll("form.js-pin-stay").forEach(function (form) {
            form.addEventListener("submit", function (event) {
                var message = form.getAttribute("data-confirm");
                if (message && !window.confirm(message)) {
                    event.preventDefault();
                    return;
                }
                pinAdminStay(form.getAttribute("data-stay") || "records");
            });
        });

        function isReload() {
            try {
                var nav = performance.getEntriesByType("navigation")[0];
                if (nav && nav.type === "reload") {
                    return true;
                }
            } catch (error) {
                return false;
            }
            return !!(performance.navigation && performance.navigation.type === 1);
        }

        var queryStay = stayFromQuery();
        var pinStay = "";
        try {
            pinStay = sessionStorage.getItem(adminStayPin) || "";
            if (pinStay) {
                sessionStorage.removeItem(adminStayPin);
            }
        } catch (error) {
            pinStay = "";
        }

        if (isReload()) {
            window.scrollTo(0, 0);
        } else {
            var staySection = queryStay || pinStay || (window.location.hash || "").replace("#", "");
            if (staySection) {
                jumpToAdminStay(staySection);
            }
        }
    }
});
