import { useEffect, useRef } from "react";
import { Link, useLocation } from "react-router-dom";
import { HeartHandshake, House, MessagesSquare, Workflow } from "lucide-react";

const NAV_ITEMS = [
  { label: "Home", href: "/", Icon: House },
  { label: "How It Works", href: "/how-it-works", Icon: Workflow },
  { label: "Success Stories", href: "/success-stories", Icon: HeartHandshake },
  { label: "Contact Us", href: "/contactus", Icon: MessagesSquare },
];

const isActivePath = (pathname, href) => (
  href === "/" ? pathname === "/" : pathname === href
);

export default function MobileBottomNavigation() {
  const { pathname } = useLocation();
  const activeItemRef = useRef(null);

  useEffect(() => {
    activeItemRef.current?.scrollIntoView({
      behavior: "smooth",
      block: "nearest",
      inline: "center",
    });
  }, [pathname]);

  return (
    <nav className="mobile-bottom-nav" aria-label="Mobile site navigation">
      <div className="mobile-bottom-nav__scroller">
        {NAV_ITEMS.map(({ label, href, Icon }) => {
          const active = isActivePath(pathname, href);
          return (
            <Link
              key={href}
              to={href}
              ref={active ? activeItemRef : undefined}
              className={`mobile-bottom-nav__item${active ? " is-active" : ""}`}
              aria-current={active ? "page" : undefined}
            >
              <Icon className="mobile-bottom-nav__icon" aria-hidden="true" strokeWidth={2} />
              <span>{label}</span>
            </Link>
          );
        })}
      </div>
    </nav>
  );
}
