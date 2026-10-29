import { useCallback, useEffect, useState } from "react";
import { Eye, Inbox, Send, Star, Check, RefreshCw } from "lucide-react";
import CandidateLayout from "../components/CandidateLayout";
import StatCard from "../components/StatCard";
import MatchCard from "../components/MatchCard";
import { getDashboard, getHomeData, getProfiles, getReceivedInterests } from "../../auth";
import { request } from "../../api";

const timeAgo = (value) => {
  if (!value) return "";
  const diff = Date.now() - new Date(value).getTime();
  if (Number.isNaN(diff) || diff < 0) return "";
  const minutes = Math.floor(diff / 60000);
  if (minutes < 1) return "Just now";
  if (minutes < 60) return `${minutes} min ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} hour${hours === 1 ? "" : "s"} ago`;
  const days = Math.floor(hours / 24);
  if (days === 1) return "Yesterday";
  if (days < 7) return `${days} days ago`;
  return new Date(value).toLocaleDateString("en-IN", {
    day: "numeric",
    month: "short",
  });
};

const checklistFor = (profile) => [
  { label: "Basic details", done: Boolean(profile) },
  { label: "Education & occupation", done: Boolean(profile?.has_education) },
  { label: "Family details", done: Boolean(profile?.has_family) },
  { label: "Upload photos", done: Boolean(profile?.has_photos) },
  { label: "Partner preferences", done: Boolean(profile?.has_preferences) },
];

const mapInterest = (interest) => {
  const profile = interest?.profile;
  const status = String(interest?.status || "pending").toLowerCase();
  return {
    id: interest.id,
    name: profile?.full_name || "Member",
    age: profile?.age,
    city: profile?.city || "—",
    time: timeAgo(interest?.created_at),
    status: status === "rejected" ? "Declined" : status[0].toUpperCase() + status.slice(1),
  };
};

const mapMatch = (profile) => ({
  id: profile.id,
  name: profile.full_name,
  age: profile.age,
  city: profile.city || "—",
  occupation: profile.profession || "—",
  education: profile.education || "—",
});

export default function Dashboard() {
  const [data, setData] = useState({ dashboard: null, home: null, interests: [], matches: [] });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [sendError, setSendError] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    setSendError("");

    const [dashboard, home, interests, profiles] = await Promise.allSettled([
      getDashboard(),
      getHomeData(),
      getReceivedInterests(4),
      getProfiles({ per_page: 4 }),
    ]);

    const failures = [dashboard, home].filter((result) => result.status === "rejected");
    if (failures.length) {
      setError(failures[0].reason?.message || "Could not load your dashboard.");
    }

    setData({
      dashboard: dashboard.status === "fulfilled" ? dashboard.value : null,
      home: home.status === "fulfilled" ? home.value : null,
      interests:
        interests.status === "fulfilled" && interests.value?.data
          ? interests.value.data.map(mapInterest)
          : [],
      matches:
        profiles.status === "fulfilled" && profiles.value?.data
          ? profiles.value.data.map(mapMatch)
          : [],
    });
    setLoading(false);
  }, []);

  useEffect(() => {
    const timer = setTimeout(load, 0);
    return () => clearTimeout(timer);
  }, [load]);

  const sendInterest = async (profileId) => {
    setSendError("");
    try {
      await request("/interests/send", {
        method: "POST",
        body: { to_profile_id: profileId },
      });
    } catch (err) {
      setSendError(err.message || "Could not send the interest.");
      throw err;
    }
  };

  const { dashboard, home, interests, matches } = data;
  const profile = home?.profile;
  const user = home?.user;
  const displayName = profile?.full_name || user?.name || "Candidate";
  const firstName = displayName.split(" ")[0];
  const completion = dashboard?.profile_completion ?? 0;
  const checklist = checklistFor(profile);

  const stats = {
    profileViews: dashboard?.profile_views ?? 0,
    interestsReceived: dashboard?.interests_received ?? 0,
    interestsSent: dashboard?.interests_sent ?? 0,
    shortlisted: dashboard?.shortlisted ?? 0,
  };

  const today = new Date().toLocaleDateString("en-US", {
    weekday: "long", year: "numeric", month: "long", day: "numeric",
  });

  if (loading) {
    return (
      <CandidateLayout currentPath="/candidate">
        <section className="cnd-banner">
          <div>
            <h1>Loading your dashboard…</h1>
            <p>Fetching your latest profile activity.</p>
          </div>
        </section>
        <div className="cnd-box cnd-state">
          <RefreshCw size={18} className="cnd-spin" /> Please wait…
        </div>
      </CandidateLayout>
    );
  }

  return (
    <CandidateLayout currentPath="/candidate">
      {error && (
        <div className="cnd-alert" role="alert">
          <span>{error}</span>
          <button type="button" onClick={load}>Retry</button>
        </div>
      )}

      <section className="cnd-banner">
        <div>
          <h1>Welcome back, {firstName}!</h1>
          <p>Your perfect match could be just one interest away.</p>
          <div className="cnd-badges">
            <span>ID: {profile?.registration_number || "—"}</span>
            <span>{user?.email || (user?.username ? `Mobile: ${user.username}` : "Member")}</span>
          </div>
          <span className="cnd-date">{today}</span>
        </div>
      </section>

      <section className="cnd-cards">
        <StatCard icon={Eye} value={stats.profileViews} label="Profile Views" from="#7c6df0" to="#a99cf7" />
        <StatCard icon={Inbox} value={stats.interestsReceived} label="Interests Received" from="#e11d63" to="#ff6b9a" />
        <StatCard icon={Send} value={stats.interestsSent} label="Interests Sent" from="#2f9bf0" to="#5cc0ff" />
        <StatCard icon={Star} value={stats.shortlisted} label="Shortlisted" from="#f59e0b" to="#fcd34d" />
      </section>

      <section className="cnd-two-col">
        <div className="cnd-box">
          <h2>Profile Completion</h2>
          <div className="cnd-progress">
            <div style={{ width: `${completion}%` }} />
          </div>
          <p className="cnd-progress-text">{completion}% complete</p>

          <ul className="cnd-checklist">
            {checklist.map((item) => (
              <li key={item.label} className={item.done ? "done" : ""}>
                <span className="cnd-tick">{item.done && <Check size={13} />}</span>
                {item.label}
              </li>
            ))}
          </ul>
          <a href="/candidate/profile" className="cnd-outline-btn">Complete Profile</a>
        </div>

        <div className="cnd-box">
          <h2>Recent Interests</h2>
          {interests.length === 0 ? (
            <p className="cnd-empty">No interests received yet.</p>
          ) : (
            <ul className="cnd-list">
              {interests.map((r) => (
                <li key={r.id}>
                  <div className="cnd-list-avatar">{r.name.charAt(0)}</div>
                  <div className="cnd-list-info">
                    <strong>{r.name}</strong>
                    <small>
                      {r.age ? `${r.age} yrs · ` : ""}{r.city}{r.time ? ` · ${r.time}` : ""}
                    </small>
                  </div>
                  <span className={`cnd-status ${r.status.toLowerCase()}`}>{r.status}</span>
                </li>
              ))}
            </ul>
          )}
          <a href="/candidate/interests-received" className="cnd-outline-btn">View All</a>
        </div>
      </section>

      <section className="cnd-box cnd-matches-box">
        <div className="cnd-box-head">
          <h2>Suggested Matches</h2>
          <a href="/candidate/matches" className="cnd-link-text-btn">See all</a>
        </div>
        {sendError && <p className="cnd-empty cnd-empty-error">{sendError}</p>}
        {matches.length === 0 ? (
          <p className="cnd-empty">No suggested matches available right now.</p>
        ) : (
          <div className="cnd-matches">
            {matches.map((m) => (
              <MatchCard key={m.id} match={m} onSend={sendInterest} />
            ))}
          </div>
        )}
      </section>
    </CandidateLayout>
  );
}
