import { useEffect, useState } from "react";
import {
  Bell, Heart, Home, Menu, ReceiptText, User, Compass,
} from "lucide-react";
import { Link, useNavigate } from "react-router-dom";
import CandidateSidebar from "./CandidateSidebar";
import { useProfileCompletion } from "../profile-completion-context";
import { clearSession, getCachedHomeData } from "../../auth";
import { apiMediaUrl, getProfilePhoto } from "../../api";
import { getCandidateProfile, getUnreadNotificationCount } from "../api";
import "../candidate.css";

const TABS = [
  ["/candidate", "Home", Home],
  ["/candidate/matches", "Discover", Compass],
  ["/candidate/interests-received", "Interests", Heart],
  ["/candidate/profile", "Profile", User],
  ["/candidate/melava-receipts", "Receipts", ReceiptText],
];

export default function CandidateLayout({ currentPath, candidateInfo, children }) {
  const navigate = useNavigate();
  const [collapsed, setCollapsed] = useState(false);
  const [unreadCount, setUnreadCount] = useState(0);
  const cachedHome = getCachedHomeData();
  const photoUrl = (profile) => apiMediaUrl(getProfilePhoto(profile, [
    "photo",
    "photo_url",
    "profilePhoto",
    "profile_photo",
    "profile_photo_url",
    "photos",
  ]));
  const [candidatePhoto, setCandidatePhoto] = useState(() => (
    photoUrl(candidateInfo) || photoUrl(cachedHome?.profile)
  ));
  const [failedCandidatePhoto, setFailedCandidatePhoto] = useState("");
  const profileCompletion = useProfileCompletion();

  useEffect(() => {
    let active = true;
    getCandidateProfile()
      .then((result) => {
        if (active) {
          setCandidatePhoto(photoUrl(result?.profile));
          setFailedCandidatePhoto("");
        }
      })
      .catch((error) => {
        console.error("Could not load the candidate header profile photo.", error);
      });

    const handleProfileUpdated = (event) => {
      if (!Object.prototype.hasOwnProperty.call(event.detail?.profile || {}, "photo_url")) return;
      setCandidatePhoto(photoUrl(event.detail.profile));
      setFailedCandidatePhoto("");
    };
    window.addEventListener("candidate:profile-updated", handleProfileUpdated);

    return () => {
      active = false;
      window.removeEventListener("candidate:profile-updated", handleProfileUpdated);
    };
  }, []);

  useEffect(() => {
    document.body.classList.add("candidate-mode");
    return () => document.body.classList.remove("candidate-mode");
  }, []);

  useEffect(() => {
    let active = true;
    const loadCount = async () => {
      try {
        const result = await getUnreadNotificationCount({ force: true });
        if (active) setUnreadCount(Number(result?.unread_count) || 0);
      } catch {
        /* The bell is non-critical; ignore transient failures. */
      }
    };

    loadCount();
    const interval = window.setInterval(loadCount, 60000);
    window.addEventListener("candidate:notifications-updated", loadCount);

    return () => {
      active = false;
      window.clearInterval(interval);
      window.removeEventListener("candidate:notifications-updated", loadCount);
    };
  }, []);

  const handleLogout = () => {
    clearSession();
    window.location.assign("/login");
  };

  return (
    <div className={`cnd-shell ${collapsed ? "is-collapsed" : ""}`}>
      <CandidateSidebar
        currentPath={currentPath}
        unreadCount={unreadCount}
        onLogout={handleLogout}
      />

      <div className="cnd-main">
        <header className="mm-header">
          <div className="mm-header-content">
            <button
              type="button"
              className="mm-icon-btn cnd-collapse-btn"
              onClick={() => setCollapsed((value) => !value)}
              aria-label={collapsed ? "Expand sidebar" : "Collapse sidebar"}
            >
              <Menu size={24} />
            </button>
            <Link to="/candidate" aria-label="Candidate dashboard" className="mm-logo-link">
              <img src="/website/Mano_Milan_Final_Website.png" alt="Aaryans Manomilan" className="mm-logo" />
            </Link>
            <div className="mm-header-actions">
              <button
                type="button"
                className="mm-icon-btn"
                aria-label={`Notifications${unreadCount ? `, ${unreadCount} unread` : ""}`}
                onClick={() => navigate("/candidate/notifications")}
              >
                <Bell size={24} />
                {unreadCount > 0 ? (
                  <span className="mm-notification-badge">{unreadCount > 99 ? "99+" : unreadCount}</span>
                ) : null}
              </button>
              {candidatePhoto && failedCandidatePhoto !== candidatePhoto ? (
                <Link
                  to="/candidate/profile"
                  className="mm-avatar-small"
                  aria-label="View profile"
                  title="View profile"
                >
                  <img
                    src={candidatePhoto}
                    alt=""
                    onError={() => setFailedCandidatePhoto(candidatePhoto)}
                  />
                </Link>
              ) : null}
            </div>
          </div>
        </header>

          {profileCompletion.status === "required" && currentPath !== "/candidate/profile" ? (
              <section className="cnd-profile-completion-banner" role="status">
                <div>
                  <strong>Complete your profile to unlock all features</strong>
                  <span>Your profile is {profileCompletion.completion}% complete.</span>
                </div>
                <Link to="/candidate/profile">
                  {profileCompletion.hasProfile ? "Complete profile" : "Create profile"}
                </Link>
              </section>
            ) : null}

          <main className="mm-main cnd-content">{children}</main>
      </div>

      <nav className="mm-bottom-nav" aria-label="Candidate mobile navigation">
        <div className="mm-bottom-nav-content">
          {TABS.map(([path, label, Icon]) => {
            const active = currentPath === path
              || (path === "/candidate/interests-received"
                && (currentPath === "/candidate/interests-sent" || currentPath?.includes("interests")));
            return (
              <Link
                key={path}
                to={path}
                className={`mm-nav-item ${active ? "active" : ""}`}
                aria-current={active ? "page" : undefined}
              >
                <Icon size={22} strokeWidth={active ? 2.5 : 1.8} />
                <span>{label}</span>
                {label === "Interests" && unreadCount > 0 ? (
                  <span className="mm-nav-badge">{unreadCount > 9 ? "9+" : unreadCount}</span>
                ) : null}
              </Link>
            );
          })}
        </div>
      </nav>
    </div>
  );
}
