import { Eye, Inbox, Send, Star, Check } from "lucide-react";
import CandidateLayout from "../components/CandidateLayout";
import StatCard from "../components/StatCard";
import MatchCard from "../components/MatchCard";
import {
  candidate, stats, profileChecklist, recentInterests, suggestedMatches,
} from "../data/candidateData";

export default function Dashboard() {
  const firstName = candidate.name.split(" ")[0];
  const today = new Date().toLocaleDateString("en-US", {
    weekday: "long", year: "numeric", month: "long", day: "numeric",
  });

  return (
    <CandidateLayout currentPath="/candidate">
      {/* Banner */}
      <section className="cnd-banner">
        <div>
          <h1>Welcome back, {firstName}!</h1>
          <p>Your perfect match could be just one interest away.</p>
          <div className="cnd-badges">
            <span>ID: {candidate.id}</span>
            <span>{candidate.plan} Member</span>
            <span>Valid till {candidate.planExpiry}</span>
          </div>
          <span className="cnd-date">{today}</span>
        </div>
      </section>

      {/* Stat cards */}
      <section className="cnd-cards">
        <StatCard icon={Eye} value={stats.profileViews} label="Profile Views" from="#7c6df0" to="#a99cf7" />
        <StatCard icon={Inbox} value={stats.interestsReceived} label="Interests Received" from="#e11d63" to="#ff6b9a" />
        <StatCard icon={Send} value={stats.interestsSent} label="Interests Sent" from="#2f9bf0" to="#5cc0ff" />
        <StatCard icon={Star} value={stats.shortlisted} label="Shortlisted" from="#f59e0b" to="#fcd34d" />
      </section>

      {/* Profile completion + recent interests */}
      <section className="cnd-two-col">
        <div className="cnd-box">
          <h2>Profile Completion</h2>
          <div className="cnd-progress">
            <div style={{ width: `${candidate.profileCompletion}%` }} />
          </div>
          <p className="cnd-progress-text">{candidate.profileCompletion}% complete</p>

          <ul className="cnd-checklist">
            {profileChecklist.map((item) => (
              <li key={item.label} className={item.done ? "done" : ""}>
                <span className="cnd-tick">{item.done && <Check size={13} />}</span>
                {item.label}
              </li>
            ))}
          </ul>
          <a href="#/candidate/profile" className="cnd-outline-btn">Complete Profile</a>
        </div>

        <div className="cnd-box">
          <h2>Recent Interests</h2>
          <ul className="cnd-list">
            {recentInterests.map((r) => (
              <li key={r.id}>
                <div className="cnd-list-avatar">{r.name.charAt(0)}</div>
                <div className="cnd-list-info">
                  <strong>{r.name}</strong>
                  <small>{r.age} yrs · {r.city} · {r.time}</small>
                </div>
                <span className={`cnd-status ${r.status.toLowerCase()}`}>{r.status}</span>
              </li>
            ))}
          </ul>
          <a href="#/candidate/interests-received" className="cnd-outline-btn">View All</a>
        </div>
      </section>

      {/* Suggested matches */}
      <section className="cnd-box cnd-matches-box">
        <div className="cnd-box-head">
          <h2>Suggested Matches</h2>
          <a href="#/candidate/matches" className="cnd-link-text-btn">See all</a>
        </div>
        <div className="cnd-matches">
          {suggestedMatches.map((m) => (
            <MatchCard key={m.id} match={m} />
          ))}
        </div>
      </section>
    </CandidateLayout>
  );
}