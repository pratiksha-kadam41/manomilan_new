import { lazy, Suspense, useEffect, useState } from "react";
import { useLocation } from "react-router-dom";
import Header from "./components/Header.jsx";
import Footer from "./components/Footer.jsx";
import ComingSoon from "./pages/ComingSoon.jsx";
import { getSession, isAdminRole, homeForRole, watchActivity } from "./auth";

import { request } from "./api.js";
import { AdminPermissionContext } from "./permission-context.jsx";
import { getCandidateProfile, invalidateCandidateProfileCache } from "./candidate/api.js";

const Home = lazy(() => import("./pages/Home.jsx"));
const About = lazy(() => import("./pages/About.jsx"));
const HowItWorks = lazy(() => import("./pages/HowItWorks.jsx"));
const SuccessStories = lazy(() => import("./pages/SuccessStories.jsx"));
const OurServices = lazy(() => import("./pages/OurServices.jsx"));
const ContactUs = lazy(() => import("./pages/ContactUs.jsx"));
const Login = lazy(() => import("./pages/Login.jsx"));
const ChangePassword = lazy(() => import("./pages/ChangePassword.jsx"));
const MelavaRegistration = lazy(() => import("./pages/MelavaRegistration.jsx"));
const Dashboard = lazy(() => import("./admin/pages/Dashboard"));
const CandidateDashboard = lazy(() => import("./candidate/pages/Dashboard"));
const CandidateMatches = lazy(() => import("./candidate/pages/Matches"));
const CandidateProfile = lazy(() => import("./candidate/pages/Profile"));
const CandidateActivity = lazy(() => import("./candidate/pages/Activity"));
const CandidateNotifications = lazy(() => import("./candidate/pages/Notifications"));
const CandidateProfileView = lazy(() => import("./candidate/pages/CandidateProfileView"));
const Profiles = lazy(() => import("./admin/pages/Profiles/Profiles.jsx"));
const ProfileVerify = lazy(() => import("./admin/pages/Profiles/ProfileVerify.jsx"));
const Receipt = lazy(() => import("./admin/pages/Profiles/Receipt.jsx"));
const PaymentScreenshot = lazy(() => import("./admin/pages/Profiles/PaymentScreenshot.jsx"));
const MelavaPayments = lazy(() => import("./admin/pages/MelavaPayments.jsx"));
const MelavaBioDataPage = lazy(() => import("./admin/pages/MelavaBioDataPage.jsx"));
const MelavaCampaigns = lazy(() => import("./admin/pages/MelavaCampaigns.jsx"));
const Reports = lazy(() => import("./admin/pages/Reports.jsx"));
const ResetPasswords = lazy(() => import("./admin/pages/ResetPasswords.jsx"));
const SystemManagement = lazy(() => import("./admin/pages/SystemManagement.jsx"));
const ProfileDetail = lazy(() => import("./pages/ProfileDetail.jsx"));

// Add a page here: "/url-name": Component
const ROUTES = {
  "/": Home,
  "/aboutus": About,
  "/how-it-works": HowItWorks,
  "/success-stories": SuccessStories,
  "/our-services": OurServices,
  "/contactus": ContactUs,

  "/login": Login,
  "/register": Login,
  "/melava-registration": MelavaRegistration,
  "/candidate": CandidateDashboard,
  "/candidate/profile": CandidateProfile,
  "/candidate/matches": CandidateMatches,
  "/candidate/interests-received": () => (
    <CandidateActivity currentPath="/candidate/interests-received" mode="received" />
  ),
  "/candidate/interests-sent": () => (
    <CandidateActivity currentPath="/candidate/interests-sent" />
  ),
  "/candidate/notifications": CandidateNotifications,
  "/candidate/change-password": ChangePassword,
  "/admin": Dashboard,
  "/admin/change-password": ChangePassword,
  "/admin/profiles": Profiles,
  "/admin/nri-profiles": Profiles,
  "/admin/verified-profiles": Profiles,
  "/admin/unverified-profiles": Profiles,
  "/admin/melava": MelavaPayments,
  "/admin/melava-campaigns": MelavaCampaigns,
  "/admin/reset-passwords": ResetPasswords,
  "/admin/report": Reports,
  "/admin/system-management": SystemManagement,
};

// Pages whose URL contains an id (only digits are accepted)
const DYNAMIC_ROUTES = [
  { pattern: /^\/admin\/melava-biodata\/(\d+)$/, Page: MelavaBioDataPage },
  { pattern: /^\/admin\/profiles\/(\d+)\/details$/, Page: ProfileDetail },
  { pattern: /^\/admin\/profiles\/(\d+)$/, Page: ProfileVerify },
  { pattern: /^\/admin\/profiles\/edit\/(\d+)$/, Page: ProfileVerify },
  { pattern: /^\/admin\/profiles\/payment\/(\d+)$/, Page: PaymentScreenshot },
  { pattern: /^\/admin\/profiles\/receipt\/(\d+)$/, Page: Receipt },
  { pattern: /^\/candidate\/matches\/(\d+)$/, Page: CandidateProfileView },
];

const matchRoute = (path) => {
  if (ROUTES[path]) return { Page: ROUTES[path], params: {} };
  for (const { pattern, Page } of DYNAMIC_ROUTES) {
    const m = path.match(pattern);
    if (m) return { Page, params: { id: m[1] } };
  }
  return { Page: null, params: {} };
};

const isSuperAdminSession = (user) => {
  const role = user?.role;
  const normalizedName = String(role?.name || "").toLowerCase().replace(/[^a-z]/g, "");
  return normalizedName === "superadmin"
    && String(role?.user_type) === "00"
    && role?.is_active !== false;
};

const SUPER_ADMIN_PERMISSIONS = { is_super_admin: true, modules: [] };

const legacyHashPath = window.location.hash.match(/^#(\/[^?]*)(?:\?(.*))?$/);
if (legacyHashPath) {
  const query = legacyHashPath[2] ? `?${legacyHashPath[2]}` : "";
  window.history.replaceState(null, "", `${legacyHashPath[1]}${query}`);
}

export default function App() {
  const location = useLocation();
  const path = location.pathname.replace(/\/+$/, "") || "/";
  const [adminPermissionState, setAdminPermissionState] = useState({
    token: null,
    data: null,
  });
  const [candidateProfileState, setCandidateProfileState] = useState({
    token: null,
    status: "idle",
    error: "",
  });

  useEffect(() => {
    window.scrollTo(0, 0);
  }, [path]);

  const session = getSession();
  const loggedIn = !!session;
  const sessionToken = session?.token;
  const sessionRole = session?.role;
  const isSuperAdmin = isSuperAdminSession(session?.user);

  useEffect(() => {
    if (!sessionToken || sessionRole !== "candidate") {
      return undefined;
    }

    let active = true;
    const verifyProfile = (showChecking = false) => {
      invalidateCandidateProfileCache();
      if (showChecking) {
        setCandidateProfileState({ token: sessionToken, status: "checking", error: "" });
      }
      getCandidateProfile()
        .then((result) => {
          if (active) {
            setCandidateProfileState({
              token: sessionToken,
              status: Number(result?.profile?.profile_completion || 0) >= 100
                ? "complete"
                : "required",
              error: "",
            });
          }
        })
        .catch((error) => {
          if (active) {
            setCandidateProfileState({
              token: sessionToken,
              status: "error",
              error: error.message || "Could not verify your candidate profile.",
            });
          }
        });
    };
    const handleProfileUpdated = (event) => {
      if (event.detail?.profile) {
        invalidateCandidateProfileCache();
        setCandidateProfileState({
          token: sessionToken,
          status: Number(event.detail.profile.profile_completion || 0) >= 100
            ? "complete"
            : "required",
          error: "",
        });
      } else {
        verifyProfile(true);
      }
    };

    verifyProfile();
    window.addEventListener("candidate:profile-updated", handleProfileUpdated);
    return () => {
      active = false;
      window.removeEventListener("candidate:profile-updated", handleProfileUpdated);
    };
  }, [sessionToken, sessionRole]);

  useEffect(() => {
    if (!sessionToken || sessionRole !== "admin" || isSuperAdmin) return undefined;

    let active = true;
    request("/system/my-permissions")
      .then((response) => {
        if (active) setAdminPermissionState({ token: sessionToken, data: response.data });
      })
      .catch((error) => {
        if (active) {
          console.error("Unable to load admin module permissions.", error);
          setAdminPermissionState({ token: sessionToken, data: null });
        }
      });

    return () => {
      active = false;
    };
  }, [sessionToken, sessionRole, isSuperAdmin]);

  const adminPermissions = isSuperAdmin
    ? SUPER_ADMIN_PERMISSIONS
    : adminPermissionState.token === sessionToken
      ? adminPermissionState.data
      : null;
  const permissionContext = {
    ...(adminPermissions || {}),
    path,
  };

  // While logged in, sign the person out automatically when the session expires
  useEffect(() => {
    if (!loggedIn) return;
    return watchActivity(() => {
      window.location.assign("/login?expired=1");
    });
  }, [loggedIn]);

  const isAdminPath = path.startsWith("/admin");
  const isCandidatePath = path.startsWith("/candidate");
  const isLogin = path === "/login" || path === "/register";
  const isMelavaRegistration = path === "/melava-registration";

  // Only admin and candidate pages require an authenticated session.
  const needsLogin = (isAdminPath || isCandidatePath) && !session;

  // A candidate who types /admin is sent to their own page
  const wrongRole =
    (isAdminPath && session && !isAdminRole(session.role))
    || (isCandidatePath && session && isAdminRole(session.role));
  const isChangePasswordPath = path === "/admin/change-password";
  const permittedAdminRoute = Boolean(adminPermissions?.is_super_admin) || Boolean(
    adminPermissions?.modules?.some((module) =>
      module.permissions.includes("view") &&
      (!module.requires_super_admin || adminPermissions.is_super_admin) &&
      (module.routes || [module.route]).some((route) =>
        route && (path === route || (route !== "/admin" && path.startsWith(`${route}/`))),
      ),
    ),
  );
  const canOpenAdminDashboard = Boolean(
    adminPermissions?.is_super_admin ||
    adminPermissions?.modules?.some((module) =>
      module.permissions.includes("view") && (module.routes || [module.route]).includes("/admin"),
    ),
  );
  const firstPermittedAdminRoute = adminPermissions?.modules
    ?.filter((module) =>
      module.permissions.includes("view") &&
      !module.requires_super_admin &&
      (module.routes || [module.route]).some((route) => route?.startsWith("/admin") && route !== "/admin"),
    )
    .sort((left, right) => left.sort_order - right.sort_order)
    .map((module) => (module.routes || [module.route]).find((route) => route?.startsWith("/admin") && route !== "/admin"))[0];
  const adminRouteDenied =
    isAdminPath &&
    loggedIn &&
    session.role === "admin" &&
    !isChangePasswordPath &&
    adminPermissions !== null &&
    !permittedAdminRoute;
  const mustChangePassword =
    loggedIn &&
    session.user?.is_password_changed === false &&
    path !== "/candidate/change-password" &&
    path !== "/admin/change-password";
  const candidateProfileStatus = candidateProfileState.token === sessionToken
    ? candidateProfileState.status
    : "checking";
  const needsCandidateProfile =
    sessionRole === "candidate" &&
    candidateProfileStatus === "required" &&
    path !== "/candidate/profile";
  const checkingCandidateProfile =
    sessionRole === "candidate" &&
    (candidateProfileStatus === "checking" || candidateProfileStatus === "idle");

  useEffect(() => {
    if (needsCandidateProfile) window.location.replace("/candidate/profile");
  }, [needsCandidateProfile]);

  useEffect(() => {
    if (wrongRole) window.location.replace(homeForRole(session.role));
  }, [wrongRole, session?.role]);
  useEffect(() => {
    if (
      path === "/admin" &&
      sessionRole === "admin" &&
      adminPermissions &&
      !canOpenAdminDashboard &&
      firstPermittedAdminRoute
    ) {
      window.location.replace(firstPermittedAdminRoute);
    }
  }, [path, sessionRole, adminPermissions, canOpenAdminDashboard, firstPermittedAdminRoute]);
  useEffect(() => {
    if (mustChangePassword) {
      const passwordChangePath = isAdminRole(session.role)
        ? "/admin/change-password"
        : "/candidate/change-password";
      window.location.replace(passwordChangePath);
    }
  }, [mustChangePassword, session?.role]);
  if (wrongRole) return null;
  if (mustChangePassword) return null;
  if (checkingCandidateProfile) {
    return <main className="app-loading" role="status">Checking your candidate profile…</main>;
  }
  if (candidateProfileStatus === "error" && sessionRole === "candidate") {
    return (
      <main className="app-access-error" role="alert">
        <h1>Unable to verify your profile</h1>
        <p>{candidateProfileState.error}</p>
        <button type="button" onClick={() => window.location.reload()}>Try again</button>
      </main>
    );
  }
  if (needsCandidateProfile) return null;
  if (isAdminPath && loggedIn && session.role === "admin" && !isChangePasswordPath) {
    if (adminRouteDenied) {
      return (
        <main className="app-access-error" role="alert">
          <h1>Access denied</h1>
          <p>Your role does not have permission to open this module.</p>
          <a href={homeForRole(session.role)}>Return to your dashboard</a>
        </main>
      );
    }
  }

  // CHANGED: find the page (and the id from the URL, if any)
  const { Page: Matched, params } = matchRoute(path);
  const Page = needsLogin ? Login : Matched;
  const hideChrome = isAdminPath || isCandidatePath || isLogin || isMelavaRegistration;

  return (
    <>
      {!hideChrome && <Header />}
      {Page ? (
        <AdminPermissionContext.Provider value={permissionContext}>
          <Suspense fallback={<main className="app-loading" role="status">Loading…</main>}>
            <Page key={`${path}-${params.id || ""}`} params={params} />
          </Suspense>
        </AdminPermissionContext.Provider>
      ) : <ComingSoon path={path} />}
      {!hideChrome && <Footer />}
    </>
  );
}