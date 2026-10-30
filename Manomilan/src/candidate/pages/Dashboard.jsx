import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ArrowRight, Bell, ChevronRight, CheckCircle2, Eye, Heart, Send, Settings2, Sparkles, UserRound, Users, X,
} from "lucide-react";
import CandidateLayout from "../components/CandidateLayout";
import MatchResultCard from "../components/MatchResultCard";
import MatchDetailModal from "../components/MatchDetailModal";
import {
  Avatar, DashboardSkeleton, EmptyState, ErrorState, PageHeader, ProgressBar,
  ProgressRing, SectionCard, Toasts,
} from "../components/Ui";
import { useToasts } from "../useToasts";
import { getCachedHomeData } from "../../auth";
import { getDashboard, getMatches, sendInterest } from "../api";
import { humanGender, text, timeAgo } from "../utils";

const greeting = () => {
  const hour = new Date().getHours();
  if (hour < 12) return "Good morning";
  if (hour < 17) return "Good afternoon";
  return "Good evening";
};

const notificationIcon = (type = "") => {
  if (type.includes("accepted")) return CheckCircle2;
  if (type.includes("interest")) return Heart;
  if (type.includes("document")) return CheckCircle2;
  if (type.includes("preference")) return Settings2;
  return Bell;
};

const STATS = [
  { key: "profile_views", label: "Profile views", icon: Eye, tone: "violet" },
  { key: "matches_available", label: "Matches available", icon: Users, tone: "blue" },
  { key: "interests_sent", label: "Interests sent", icon: Send, tone: "orange" },
];

export default function Dashboard() {
  const cached = useMemo(() => getCachedHomeData(), []);
  const [data, setData] = useState(null);
  const [suggested, setSuggested] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [suggestedError, setSuggestedError] = useState("");
  const [busy, setBusy] = useState({});
  const [selected, setSelected] = useState(null);
  const [showMissingDetails, setShowMissingDetails] = useState(false);
  const { toasts, push, dismiss } = useToasts();

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    setSuggestedError("");
    const results = await Promise.allSettled([
      getDashboard(),
      getMatches({ per_page: 6, sort: "best_match" }),
    ]);

    if (results[0].status === "fulfilled") {
      setData(results[0].value);
    } else {
      setError(results[0].reason?.message || "Could not load your dashboard.");
    }

    if (results[1].status === "fulfilled") {
      setSuggested(results[1].value?.matches || []);
    } else {
      setSuggestedError(results[1].reason?.message || "Could not load suggested matches.");
    }
    setLoading(false);
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const profile = data?.profile || cached?.profile;
  const user = data?.user || cached?.user;
  const stats = data?.stats || {};
  const backendCompletion = Number(stats.profile_completion ?? profile?.profile_completion ?? 0);
  const completion = Array.isArray(data?.completion?.missing) && data.completion.missing.length === 0
    ? 100
    : backendCompletion;
  const displayName = text(profile?.full_name || user?.name || user?.username, "Candidate");
  const firstName = displayName.split(" ")[0];
  const candidateInfo = {
    name: displayName,
    id: profile?.registration_number || profile?.profile_id || user?.username,
    photo: profile?.photo_url || "",
  };

  const updateMatch = (profileId, patch) => {
    setSuggested((items) => items.map((item) => (
      item.id === profileId ? { ...item, ...patch } : item
    )));
  };

  const handleInterest = async (match) => {
    if (busy[match.id]) return;
    setBusy((state) => ({ ...state, [match.id]: "interest" }));
    try {
      await sendInterest(match.id);
      updateMatch(match.id, { interest_id: -1 });
      push(`Interest sent to ${text(match.full_name, "this profile")}.`);
    } catch (sendError) {
      if (sendError.status === 409) {
        updateMatch(match.id, { interest_id: -1 });
        push("You have already sent an interest to this profile.", "info");
      } else {
        push(sendError.message || "Could not send the interest.", "error");
      }
    } finally {
      setBusy((state) => {
        const next = { ...state };
        delete next[match.id];
        return next;
      });
    }
  };

  const openProfile = (match) => {
    setSelected({ id: match.id, match });
  };

  const handleModalUpdate = (updated) => {
    if (!updated) return;
    updateMatch(updated.id, updated);
    setData((current) => current ? {
      ...current,
      recent_interests: (current.recent_interests || []).map((item) => (
        item.profile?.id === updated.id ? { ...item, profile: { ...item.profile, ...updated } } : item
      )),
    } : current);
  };

  const ageRange = data?.profile?.preference
    ? [data.profile.preference.age_from, data.profile.preference.age_to].filter(Boolean).join(" - ")
    : "";

  return (
    <CandidateLayout currentPath="/candidate" candidateInfo={candidateInfo}>
      <Toasts items={toasts} onDismiss={dismiss} />
      <PageHeader
        eyebrow="Dashboard"
        title={`${greeting()}, ${firstName}`}
        subtitle="Your matrimonial journey, matches and activity in one place."
        actions={(
          <>
            <a href="/candidate/profile" className="cnd-btn cnd-btn-ghost">
              <UserRound size={17} /> My profile
            </a>
            <a href="/candidate/matches" className="cnd-btn cnd-btn-primary">
              <Users size={17} /> Find matches
            </a>
          </>
        )}
      />

      <div className="cnd-dashboard-summary-head">
        <div>
          <span className="cnd-dashboard-summary-icon"><Sparkles size={18} /></span>
          <strong>Your Dashboard</strong>
        </div>
        <button
          type="button"
          className={`cnd-completion-toggle ${completion >= 100 ? "complete" : ""}`}
          onClick={() => setShowMissingDetails((current) => !current)}
          aria-expanded={showMissingDetails}
        >
          <CheckCircle2 size={16} />
          Profile {completion}% complete
          <ChevronRight size={15} />
        </button>
      </div>

      {loading && !data ? <DashboardSkeleton /> : error ? (
        <ErrorState message={error} onRetry={load} />
      ) : (
        <>
          <section className="cnd-hero">
            <div className="cnd-hero-copy">
              <span className="cnd-hero-pill"><Sparkles size={15} /> Candidate dashboard</span>
              <h2>{profile ? `Your profile is ${completion}% complete` : "Let us complete your profile"}</h2>
              <p>
                {profile
                  ? "A complete, verified profile receives more genuine interests. Add the missing details to stand out."
                  : "Add your basic details, partner preferences and photos to unlock compatible matches."}
              </p>
              <div className="cnd-hero-tags">
                <span>{text(profile?.registration_number || profile?.profile_id, "New profile")}</span>
                <span>{humanGender(profile?.gender) || "Gender not set"}</span>
                <span>{text(profile?.city, "Location not added")}</span>
              </div>
            </div>
            <div className="cnd-hero-side">
              <ProgressRing value={completion} size={124} stroke={10} label="complete" />
              <div className="cnd-hero-side-copy">
                <strong>{data?.completion?.missing?.length ? "Next best steps" : "Looking great"}</strong>
                <ul>
                  {(data?.completion?.missing?.length ? data.completion.missing.slice(0, 3) : ["Profile photo", "Partner preferences", "Identity documents"]).map((item) => (
                    <li key={item}>{item}</li>
                  ))}
                </ul>
              </div>
            </div>
          </section>

          <section className="cnd-stat-grid">
            {STATS.map(({ key, label, icon: Icon, tone }) => (
              <article key={key} className={`cnd-stat-card tone-${tone}`}>
                <span className="cnd-stat-icon"><Icon size={20} /></span>
                <div>
                  <strong>{Number(stats[key] || 0).toLocaleString("en-IN")}</strong>
                  <span>{label}</span>
                </div>
              </article>
            ))}
          </section>

          <section className="cnd-profile-completion-card">
            <div className="cnd-profile-completion-main">
              <ProgressRing value={completion} size={112} stroke={10} label="complete" />
              <div>
                <h3>{completion >= 100 ? "Your profile is complete" : "Complete your profile"}</h3>
                <p>{completion >= 100 ? "Looking great" : "Add the remaining details to improve your matches."}</p>
                <a href="/candidate/profile" className="cnd-btn cnd-btn-primary cnd-btn-sm">
                  {completion >= 100 ? "View profile" : "Complete profile"} <ArrowRight size={14} />
                </a>
              </div>
            </div>
            <button
              type="button"
              className="cnd-missing-summary"
              onClick={() => setShowMissingDetails((current) => !current)}
              aria-expanded={showMissingDetails}
            >
              <span>
                <strong>{data?.completion?.missing?.length ? "Missing details" : "Profile details"}</strong>
                <small>{data?.completion?.missing?.length || 0} items remaining</small>
              </span>
              <ChevronRight size={18} />
            </button>
          </section>

          {showMissingDetails ? (
            <section className="cnd-missing-details-panel" aria-label="Profile completion details">
              <div className="cnd-missing-details-head">
                <div>
                  <strong>{completion >= 100 ? "Everything is complete" : "Complete these details"}</strong>
                  <p>{completion >= 100 ? "Your profile is ready for better matches." : "Select an item to update your profile."}</p>
                </div>
                <button type="button" className="cnd-icon-btn" onClick={() => setShowMissingDetails(false)} aria-label="Close missing details">
                  <X size={17} />
                </button>
              </div>
              {data?.completion?.missing?.length ? (
                <div className="cnd-missing-detail-list">
                  {data.completion.missing.map((item) => (
                    <a href="/candidate/profile?tab=edit" key={item}>
                      <span>{item}</span>
                      <ChevronRight size={17} />
                    </a>
                  ))}
                </div>
              ) : (
                <div className="cnd-complete-details"><CheckCircle2 size={20} /> All required profile sections are complete.</div>
              )}
            </section>
          ) : null}

          <div className="cnd-dashboard-grid">
            <SectionCard
              title="Your profile"
              subtitle="Keep your details fresh for better matches"
              icon={UserRound}
              action={<a href="/candidate/profile" className="cnd-text-link">Edit profile <ArrowRight size={14} /></a>}
              className="cnd-profile-snapshot"
            >
              {profile ? (
                <>
                  <div className="cnd-snapshot-top">
                    <Avatar name={displayName} src={profile.photo_url} size="lg" />
                    <div>
                      <h3>{displayName}</h3>
                      <p>{[profile.age ? `${profile.age} years` : "", humanGender(profile.gender), profile.city].filter(Boolean).join(" • ")}</p>
                      <div className="cnd-snapshot-badges">
                        {profile.preference ? <span className="cnd-chip">Preferences set</span> : <span className="cnd-chip muted">No preferences</span>}
                      </div>
                    </div>
                  </div>
                  <ProgressBar value={completion} label="Profile completion" />
                  <dl className="cnd-snapshot-facts">
                    <div><dt>Education</dt><dd>{text(profile.details?.education, "Not added")}</dd></div>
                    <div><dt>Profession</dt><dd>{text(profile.details?.profession, "Not added")}</dd></div>
                    <div><dt>Preferred age</dt><dd>{ageRange ? `${ageRange} years` : "Not set"}</dd></div>
                    <div><dt>Documents</dt><dd>{profile.has_documents ? "Uploaded" : "Pending"}</dd></div>
                  </dl>
                </>
              ) : (
                <EmptyState
                  icon={UserRound}
                  title="No candidate profile yet"
                  message="Create your profile to start receiving compatible matches."
                  action={<a href="/candidate/profile" className="cnd-btn cnd-btn-primary">Create profile</a>}
                />
              )}
            </SectionCard>

          </div>

          <SectionCard
            title={`Suggested ${humanGender(profile?.gender === "groom" ? "bride" : "groom") || "matches"}`}
            subtitle="Ranked using your preferences and profile compatibility"
            icon={Sparkles}
            action={<a href="/candidate/matches" className="cnd-text-link">See all matches <ArrowRight size={14} /></a>}
            className="cnd-suggested"
          >
            {suggestedError ? (
              <ErrorState message={suggestedError} onRetry={load} />
            ) : suggested.length === 0 ? (
              <EmptyState
                icon={Users}
                title="No suggested matches yet"
                message={profile ? "Try widening your preferences or check back soon." : "Create your profile first so we can find compatible matches."}
                action={<a href={profile ? "/candidate/matches" : "/candidate/profile"} className="cnd-btn cnd-btn-primary">{profile ? "Browse matches" : "Create profile"}</a>}
              />
            ) : (
              <div className="cnd-match-grid">
                {suggested.map((match) => (
                  <MatchResultCard
                    key={match.id}
                    match={match}
                    onOpen={openProfile}
                    onInterest={handleInterest}
                    interestBusy={busy[match.id] === "interest"}
                  />
                ))}
              </div>
            )}
          </SectionCard>

          <div className="cnd-dashboard-grid">
            <SectionCard
              title="Notifications"
              subtitle="Latest updates on your account"
              icon={Bell}
              action={<a href="/candidate/notifications" className="cnd-text-link">View all <ArrowRight size={14} /></a>}
            >
              {data?.recent_notifications?.length ? (
                <ul className="cnd-notification-mini">
                  {data.recent_notifications.map((item) => {
                    const Icon = notificationIcon(item.type);
                    return (
                      <li key={item.id} className={item.read ? "" : "unread"}>
                        <span className="cnd-notification-icon"><Icon size={16} /></span>
                        <div>
                          <strong>{item.title}</strong>
                          <p>{item.message}</p>
                          <small>{timeAgo(item.created_at)}</small>
                        </div>
                      </li>
                    );
                  })}
                </ul>
              ) : (
                <EmptyState icon={Bell} title="All caught up" message="New interests, matches and verification updates will appear here." />
              )}
            </SectionCard>

            <SectionCard title="Quick actions" subtitle="Shortcuts to manage your account" icon={Settings2}>
              <div className="cnd-quick-actions">
                <a href="/candidate/profile"><span><UserRound size={19} /></span><div><strong>Edit profile</strong><small>Update your details and photos</small></div></a>
                <a href="/candidate/matches"><span><Users size={19} /></span><div><strong>Find matches</strong><small>Search compatible candidates</small></div></a>
                <a href="/candidate/interests-sent"><span><Send size={19} /></span><div><strong>Interests sent</strong><small>Track your sent interests</small></div></a>
                <a href="/candidate/profile?tab=preferences"><span><Settings2 size={19} /></span><div><strong>Preferences</strong><small>Refine your partner criteria</small></div></a>
                <a href="/candidate/profile?tab=documents"><span><CheckCircle2 size={19} /></span><div><strong>Documents</strong><small>Complete verification</small></div></a>
              </div>
            </SectionCard>
          </div>
        </>
      )}

      <MatchDetailModal
        open={Boolean(selected)}
        profileId={selected?.id}
        initialMatch={selected?.match}
        onClose={() => setSelected(null)}
        onUpdated={handleModalUpdate}
      />
    </CandidateLayout>
  );
}
