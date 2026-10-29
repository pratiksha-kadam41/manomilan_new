import { useEffect, useState } from "react";
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
import MelavaPayments from "./admin/pages/MelavaPayments.jsx";
import MelavaCampaigns from "./admin/pages/MelavaCampaigns.jsx";

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

// Pages whose URL contains an id, e.g. #/admin/profiles/396  (only digits are accepted)
const DYNAMIC_ROUTES = [
  { pattern: /^\/admin\/profiles\/(\d+)$/, Page: ProfileVerify },
  { pattern: /^\/admin\/profiles\/edit\/(\d+)$/, Page: ProfileVerify },
];

const matchRoute = (path) => {
  if (ROUTES[path]) return { Page: ROUTES[path], params: {} };
  for (const { pattern, Page } of DYNAMIC_ROUTES) {
    const m = path.match(pattern);
    if (m) return { Page, params: { id: m[1] } };
  }
  return { Page: null, params: {} };
};

const getPath = () => {
  const p = window.location.hash.replace(/^#/, "").split("?")[0];
  return p === "" ? "/" : p;
};

export default function App() {
  const [path, setPath] = useState(getPath());

  useEffect(() => {
    const onChange = () => {
      setPath(getPath());
      window.scrollTo(0, 0); // every new page starts at the top
    };
    window.addEventListener("hashchange", onChange);
    return () => window.removeEventListener("hashchange", onChange);
  }, []);

  useEffect(() => {
    const onShow = (e) => { if (e.persisted) setPath(getPath()); };
    window.addEventListener("pageshow", onShow);
    return () => window.removeEventListener("pageshow", onShow);
  }, []);

  const session = getSession();
  const loggedIn = !!session;

  // While logged in, sign the person out automatically when the session expires
  useEffect(() => {
    if (!loggedIn) return;
    return watchActivity(() => {
      window.location.hash = "#/login?expired=1";
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
    if (wrongRole) window.location.hash = homeForRole(session.role);
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
      {Page ? <Page params={params} /> : <ComingSoon path={path} />}
      {!hideChrome && <Footer />}
    </>
  );
}