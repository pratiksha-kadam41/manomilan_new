import { useEffect, useRef, useState } from "react";
import { useLocation } from "react-router-dom";

const PLACEHOLDER = "https://placehold.co/200x180/DC143C/FFFFFF?text=Aaryan's+Manomilan";

const logoFallback = (e) => {
  e.currentTarget.onerror = null;
  e.currentTarget.src = PLACEHOLDER;
};

const DESKTOP_LINKS = [
  ["Home", "/"],
  ["About us", "/aboutus"],
  ["How It Works", "/how-it-works"],
  ["Success Stories", "/success-stories"],
  ["Our services", "/our-services"],
  ["Contact us", "/contactus"],
];

const MOBILE_LINKS = [
  ["Home", "/"],
  ["About", "/aboutus"],
  ["How It Works", "/how-it-works"],
  ["Success Stories", "/success-stories"],
  ["Our services", "/our-services"],
  ["Contact us", "/contactus"],
];

const REGISTER_URL = "/melava-registration";
const LOGIN_URL = "/login";

export default function Header() {
  const { pathname } = useLocation();
  const isAuthPage = [LOGIN_URL, "/register"].includes(pathname.replace(/\/+$/, ""));
  const [menu, setMenu] = useState({ path: pathname, open: false });
  const [regMenu, setRegMenu] = useState({ path: pathname, open: false });
  const closeRef = useRef(null);

  const open = menu.open && menu.path === pathname;
  const regOpen = regMenu.open && regMenu.path === pathname;

  useEffect(() => {
    if (!open) return undefined;

    const previousOverflow = document.body.style.overflow;
    const handleKeyDown = (event) => {
      if (event.key === "Escape") setMenu({ path: pathname, open: false });
    };

    document.body.style.overflow = "hidden";
    document.addEventListener("keydown", handleKeyDown);
    const focusTimer = window.setTimeout(() => closeRef.current?.focus(), 60);

    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener("keydown", handleKeyDown);
      window.clearTimeout(focusTimer);
    };
  }, [open, pathname]);

  const toggle = () => setMenu({ path: pathname, open: !open });
  const closeMenu = () => setMenu({ path: pathname, open: false });
  const toggleReg = () => setRegMenu({ path: pathname, open: !regOpen });
  const closeReg = () => setRegMenu({ path: pathname, open: false });

  const isActive = (href) => (href === "/" ? pathname === "/" || pathname === "" : pathname === href);

  return (
    <>
      <header className={`site-header site-header--premium${isAuthPage ? " site-header--login" : ""}`}>
        <div className="mmh-bar">
          <div className="site-container mmh-bar-inner">
            <img
              src="/website/output-onlinepngtools.png"
              onError={logoFallback}
              alt=""
              aria-hidden="true"
              className="mmh-side-mark"
            />

            <p className="mmh-header-blessing">
              <span aria-hidden="true">॥</span>
              <span className="mmh-header-blessing-text">जय जिनेन्द्र</span>
              <span aria-hidden="true">॥</span>
            </p>

            <img
              src="/website/output-onlinepngtools.png"
              onError={logoFallback}
              alt=""
              aria-hidden="true"
              className="mmh-side-mark"
            />
          </div>
        </div>

        <div className="mmh-menu">
          <div className="site-container mmh-menu-inner">
            <nav className="mmh-links" aria-label="Primary">
              {DESKTOP_LINKS.map(([label, href]) => {
                const active = isActive(href);
                return (
                  <a
                    key={label}
                    href={href}
                    className={`mmh-nav-link${active ? " is-active" : ""}`}
                    aria-current={active ? "page" : undefined}
                    onClick={closeReg}
                  >
                    {label}
                  </a>
                );
              })}

              <span className="mmh-reg-dropdown">
                <button
                  type="button"
                  className="mmh-reg-trigger"
                  aria-haspopup="true"
                  aria-expanded={regOpen}
                  onClick={toggleReg}
                >
                  Registration Plan
                  <i className="bi bi-chevron-down" aria-hidden="true" />
                </button>
                <div className={`mmh-reg-menu${regOpen ? " show" : ""}`}>
                  <a href={REGISTER_URL} onClick={closeReg}>Melava Registration</a>
                  <a href={LOGIN_URL} onClick={closeReg}>Login</a>
                </div>
              </span>
            </nav>

            <a className="mmh-menu-login" href={LOGIN_URL}>
              <i className="bi bi-person-circle" aria-hidden="true" />
              Login
            </a>
          </div>
        </div>

        <div className="mmh-mobile-bar">
          <button
            type="button"
            className="mmh-hamburger"
            onClick={toggle}
            aria-label={open ? "Close menu" : "Open menu"}
            aria-expanded={open}
            aria-controls="mmh-mobile-menu"
          >
            <span />
            <span />
            <span />
          </button>

          <p className="mmh-mobile-blessing">॥ जय जिनेन्द्र ॥</p>

          <div className="mmh-mobile-actions">
            <a href={LOGIN_URL} className="mmh-mobile-login">Login</a>
            <a href={REGISTER_URL} className="mmh-mobile-register">Register</a>
          </div>

          {isAuthPage && (
            <nav className="mmh-login-mobile-nav" aria-label="Primary">
              {MOBILE_LINKS.map(([label, href]) => (
                <a key={href} href={href} aria-current={isActive(href) ? "page" : undefined}>
                  {label}
                </a>
              ))}
            </nav>
          )}
        </div>
      </header>

      <div
        id="mmh-mobile-menu"
        className={`mmh-mobile-menu${open ? " active" : ""}`}
        role="dialog"
        aria-modal="true"
        aria-label="Site menu"
      >
        <button
          ref={closeRef}
          type="button"
          className="mmh-mobile-close"
          onClick={closeMenu}
          aria-label="Close menu"
        >
          <i className="bi bi-x-lg" aria-hidden="true" />
        </button>

        <nav>
          <p className="mmh-mobile-title">॥ जय जिनेन्द्र ॥</p>
          {MOBILE_LINKS.map(([label, href]) => {
            const active = isActive(href);
            return (
              <a
                key={label}
                href={href}
                onClick={closeMenu}
                className={active ? "is-active" : undefined}
                aria-current={active ? "page" : undefined}
              >
                {label}
              </a>
            );
          })}
          <a href={REGISTER_URL} onClick={closeMenu}>Melava Registration</a>
          <a href={LOGIN_URL} onClick={closeMenu}>Login</a>
        </nav>
      </div>
    </>
  );
}
