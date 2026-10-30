import { useEffect } from "react";
import { useLocation } from "react-router-dom";
import Header from "./components/Header.jsx";
import Footer from "./components/Footer.jsx";
import Home from "./pages/Home.jsx";
import About from "./pages/About.jsx";
import ComingSoon from "./pages/ComingSoon.jsx";
import HowItWorks from "./pages/HowItWorks.jsx";
import SuccessStories from "./pages/SuccessStories.jsx";
import OurServices from "./pages/OurServices.jsx";
import ContactUs from "./pages/ContactUs.jsx";
import Login from "./pages/Login.jsx";
import ChangePassword from "./pages/ChangePassword.jsx";
import MelavaRegistration from "./pages/MelavaRegistration.jsx";
import Dashboard from "./admin/pages/Dashboard";
import CandidateDashboard from "./candidate/pages/Dashboard";
import { getSession, isAdminRole, homeForRole, watchActivity } from "./auth";

import Profiles from "./admin/pages/Profiles/Profiles.jsx";
import ProfileVerify from "./admin/pages/Profiles/ProfileVerify.jsx";
import Receipt from "./admin/pages/Profiles/Receipt.jsx";
import PaymentScreenshot from "./admin/pages/Profiles/PaymentScreenshot.jsx";
import MelavaPayments from "./admin/pages/MelavaPayments.jsx";
import MelavaCampaigns from "./admin/pages/MelavaCampaigns.jsx";
import ProfileDetail from "./pages/ProfileDetail.jsx";

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
  "/candidate/change-password": ChangePassword,
  "/admin": Dashboard,
  "/admin/change-password": ChangePassword,
  "/admin/profiles": Profiles,
  "/admin/melava": MelavaPayments,
  "/admin/melava-campaigns": MelavaCampaigns,
};

// Pages whose URL contains an id (only digits are accepted)
const DYNAMIC_ROUTES = [
  { pattern: /^\/admin\/profiles\/(\d+)\/details$/, Page: ProfileDetail },
  { pattern: /^\/admin\/profiles\/(\d+)$/, Page: ProfileVerify },
  { pattern: /^\/admin\/profiles\/edit\/(\d+)$/, Page: ProfileVerify },
  { pattern: /^\/admin\/profiles\/payment\/(\d+)$/, Page: PaymentScreenshot },
  { pattern: /^\/admin\/profiles\/receipt\/(\d+)$/, Page: Receipt },
];

const matchRoute = (path) => {
  if (ROUTES[path]) return { Page: ROUTES[path], params: {} };
  for (const { pattern, Page } of DYNAMIC_ROUTES) {
    const m = path.match(pattern);
    if (m) return { Page, params: { id: m[1] } };
  }
  return { Page: null, params: {} };
};

const legacyHashPath = window.location.hash.match(/^#(\/[^?]*)(?:\?(.*))?$/);
if (legacyHashPath) {
  const query = legacyHashPath[2] ? `?${legacyHashPath[2]}` : "";
  window.history.replaceState(null, "", `${legacyHashPath[1]}${query}`);
}

export default function App() {
  const location = useLocation();
  const path = location.pathname.replace(/\/+$/, "") || "/";

  useEffect(() => {
    window.scrollTo(0, 0);
  }, [path]);

  const session = getSession();
  const loggedIn = !!session;

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

  // Private pages need a login
  const needsLogin = (isAdminPath || isCandidatePath) && !session;

  // A candidate who types /admin is sent to their own page
  const wrongRole = isAdminPath && session && !isAdminRole(session.role);
  useEffect(() => {
    if (wrongRole) window.location.replace(homeForRole(session.role));
  }, [wrongRole, session?.role]);
  if (wrongRole) return null;

  // CHANGED: find the page (and the id from the URL, if any)
  const { Page: Matched, params } = matchRoute(path);
  const Page = needsLogin ? Login : Matched;
  const hideChrome = isAdminPath || isCandidatePath || isLogin || isMelavaRegistration;

  return (
    <>
      {!hideChrome && <Header />}
      {/* CHANGED: pass params so the page receives the profile id */}
      {Page ? <Page key={Matched === ProfileDetail ? path : undefined} params={params} /> : <ComingSoon path={path} />}
      {!hideChrome && <Footer />}
    </>
  );
}