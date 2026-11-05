import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  ArrowRight, BadgeCheck, Bell, BriefcaseBusiness, CheckCircle2, ChevronLeft, ChevronRight, Eye, Heart, MapPin, RefreshCw, Send, Settings2, Sparkles, UserRound, Users, X,
} from "lucide-react";
import { Link } from "react-router-dom";
import CandidateLayout from "../components/CandidateLayout";
import MatchResultCard from "../components/MatchResultCard";
import MatchDetailModal from "../components/MatchDetailModal";
import {
  Avatar, CardGridSkeleton, DashboardSkeleton, EmptyState, ErrorState, ProgressBar,
  ProgressRing, SectionCard, Toasts,
} from "../components/Ui";
import { useToasts } from "../useToasts";
import { getCachedHomeData } from "../../auth";
import {
  createRecommendationSeed, getDashboard, getMatch, getMatches, readDashboardCache, sendInterest,
} from "../api";
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

const candidatePhotos = (candidate) => [
  candidate?.photo_url,
  candidate?.full_body_photo_url,
  candidate?.half_body_photo_url,
  candidate?.selfie_photo_url,
  ...(candidate?.additional_photo_urls || []),
  ...(candidate?.candidate_photo_urls || []),
].filter((photo, index, all) => typeof photo === "string" && photo && all.indexOf(photo) === index);

function CandidateImage({ photos = [], name, size = "lg" }) {
  const [failedPhotos, setFailedPhotos] = useState([]);
  const src = photos.find((photo) => !failedPhotos.includes(photo));

  return src ? (
    <img
      src={src}
      alt={name ? `${name} profile` : ""}
      loading="lazy"
      decoding="async"
      onError={() => setFailedPhotos((current) => [...current, src])}
    />
  ) : <Avatar name={name} size={size} />;
}

function HomeProfileCard({ match, featured = false, onOpen, onInterest, interestBusy }) {
  const [activePhotoIndex, setActivePhotoIndex] = useState(0);
  if (!match) return null;
  const name = text(match.full_name, "Member");
  const interestDone = Boolean(match.interest_id);
  const photos = candidatePhotos(match);
  const activePhoto = photos[activePhotoIndex] || photos[0];

  return (
    <article className={`mm-profile-card ${featured ? "is-featured" : ""}`}>
      <button
        type="button"
        className="mm-profile-image-button"
        onClick={() => onOpen(match, activePhotoIndex)}
        aria-label={`View ${name}'s photos`}
      >
        {activePhoto ? (
          <img
            className="mm-profile-image-backdrop"
            src={activePhoto}
            alt=""
            aria-hidden="true"
            loading="lazy"
            decoding="async"
            onError={(event) => { event.currentTarget.hidden = true; }}
          />
        ) : null}
        <span className="mm-profile-image-foreground">
          <CandidateImage photos={activePhoto ? [activePhoto] : []} name={name} size="xl" />
        </span>
      </button>
      {photos.length > 1 ? (
        <div className="mm-profile-photo-strip" role="group" aria-label={`${name}'s photos`}>
          {photos.map((photo, index) => (
            <button
              type="button"
              className={`mm-profile-photo-thumb ${index === activePhotoIndex ? "is-active" : ""}`}
              key={photo}
              onClick={() => setActivePhotoIndex(index)}
              aria-label={`Show photo ${index + 1} of ${photos.length}`}
              aria-pressed={index === activePhotoIndex}
            >
              <img src={photo} alt="" loading="lazy" decoding="async" />
            </button>
          ))}
          <span className="mm-profile-photo-count">{activePhotoIndex + 1} / {photos.length}</span>
        </div>
      ) : null}
      <div className="mm-profile-card-body">
        <button type="button" className="mm-profile-title" onClick={() => onOpen(match)}>
          <strong>{name}{match.verified ? <BadgeCheck size={17} /> : null}</strong>
          {match.age ? <span>, {match.age}</span> : null}
        </button>
        <span className="mm-profile-location"><MapPin size={14} /> {text(match.city, "Location not added")}</span>
        <div className="mm-profile-facts">
          {match.height ? <span>{match.height}</span> : null}
          {match.profession ? <span><BriefcaseBusiness size={13} /> {match.profession}</span> : null}
          {match.marital_status ? <span>{match.marital_status}</span> : null}
        </div>
        {featured && match.about ? <p className="mm-profile-about">{match.about}</p> : null}
        <div className="mm-profile-actions">
          <button
            type="button"
            className={`mm-interest-button ${interestDone ? "is-done" : ""}`}
            onClick={() => onInterest(match)}
            disabled={interestDone || interestBusy}
          >
            <Heart size={17} fill={interestDone ? "currentColor" : "none"} />
            {interestDone ? "Interest sent" : "Send interest"}
          </button>
          <Link className="mm-view-button" to={`/candidate/matches/${match.id}`}>
            View profile <ArrowRight size={15} />
          </Link>
        </div>
      </div>
    </article>
  );
}

export default function Dashboard() {
  const cached = useMemo(() => getCachedHomeData(), []);
  const dashboardCache = useMemo(() => readDashboardCache(), []);
  const [shuffleSeed] = useState(createRecommendationSeed);
  const [data, setData] = useState(() => dashboardCache || cached);
  const [suggested, setSuggested] = useState([]);
  const [recentProfiles, setRecentProfiles] = useState([]);
  const [suggestedLoading, setSuggestedLoading] = useState(true);
  const [suggestedLoadingMore, setSuggestedLoadingMore] = useState(false);
  const [recentLoading, setRecentLoading] = useState(true);
  const [loading, setLoading] = useState(() => !dashboardCache);
  const [error, setError] = useState("");
  const [suggestedError, setSuggestedError] = useState("");
  const [suggestedMoreError, setSuggestedMoreError] = useState("");
  const [recentError, setRecentError] = useState("");
  const [busy, setBusy] = useState({});
  const [selected, setSelected] = useState(null);
  const [story, setStory] = useState(null);
  const [showMissingDetails, setShowMissingDetails] = useState(false);
  const recentSection = useRef(null);
  const suggestedSection = useRef(null);
  const suggestedLoadMoreRef = useRef(null);
  const recentRequestProfile = useRef(null);
  const suggestedRequestProfile = useRef(null);
  const suggestedLoadedIds = useRef(new Set());
  const suggestedHasMore = useRef(false);
  const suggestedLoadingMoreRef = useRef(false);
  const suggestedPage = useRef(1);
  const { toasts, push, dismiss } = useToasts();

  const load = useCallback(async (force = false) => {
    setLoading(true);
    setError("");
    setSuggestedError("");
    try {
      setData(await getDashboard({ force }));
      setSuggestedError("");
    } catch (loadError) {
      setError(loadError.message || "Could not load your dashboard.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!dashboardCache) {
      const timer = window.setTimeout(() => load(), 0);
      return () => window.clearTimeout(timer);
    }
    return undefined;
  }, [dashboardCache, load]);

  const profile = data?.profile || (!loading ? cached?.profile : null);
  const user = data?.user || (!loading ? cached?.user : null);
  const stats = data?.stats || {};
  const backendCompletion = Number(stats.profile_completion ?? profile?.profile_completion ?? 0);
  const completion = Array.isArray(data?.completion?.missing) && data.completion.missing.length === 0
    ? 100
    : backendCompletion;
  const displayName = text(profile?.full_name, "Your profile");
  const candidateInfo = {
    name: displayName,
    id: profile?.registration_number || profile?.profile_id || user?.username,
    photo: profile?.photo_url || "",
  };

  useEffect(() => {
    if (!loading && completion < 30) {
      setSuggestedLoading(false);
      setRecentLoading(false);
    }
  }, [loading, completion]);

  const loadSuggested = useCallback(async () => {
    if (!profile?.id || completion < 30 || suggestedRequestProfile.current === profile.id) return;
    suggestedRequestProfile.current = profile.id;
    setSuggestedLoading(true);
    setSuggestedError("");
    setSuggestedMoreError("");
    suggestedLoadedIds.current = new Set();
    suggestedHasMore.current = false;
    suggestedPage.current = 1;
    try {
      const result = await getMatches({
        sort: "best_match", per_page: 6, page: 1, feed: 1,
        include_filters: 0, shuffle_seed: shuffleSeed,
      });
      const matches = Array.isArray(result?.matches) ? result.matches : [];
      suggestedLoadedIds.current = new Set(matches.map((match) => String(match.id)));
      suggestedHasMore.current = Boolean(result?.pagination?.has_more);
      setSuggested(matches);
    } catch (matchError) {
      suggestedRequestProfile.current = null;
      setSuggestedError(matchError.message || "Could not load compatible profiles.");
    } finally {
      setSuggestedLoading(false);
    }
  }, [profile?.id, completion, shuffleSeed]);

  const loadMoreSuggested = useCallback(async () => {
    if (
      !profile?.id
      || completion < 30
      || !suggestedHasMore.current
      || suggestedLoadingMoreRef.current
    ) return;

    suggestedLoadingMoreRef.current = true;
    setSuggestedLoadingMore(true);
    setSuggestedMoreError("");
    const page = suggestedPage.current + 1;
    try {
      const result = await getMatches({
        sort: "best_match",
        per_page: 6,
        page,
        feed: 1,
        include_filters: 0,
        shuffle_seed: shuffleSeed,
        exclude_ids: [...suggestedLoadedIds.current].join(","),
      });
      const nextMatches = (Array.isArray(result?.matches) ? result.matches : [])
        .filter((match) => !suggestedLoadedIds.current.has(String(match.id)));
      nextMatches.forEach((match) => suggestedLoadedIds.current.add(String(match.id)));
      suggestedPage.current = page;
      suggestedHasMore.current = Boolean(result?.pagination?.has_more);
      setSuggested((current) => [...current, ...nextMatches]);
    } catch (matchError) {
      setSuggestedMoreError(matchError.message || "Could not load more compatible profiles.");
    } finally {
      suggestedLoadingMoreRef.current = false;
      setSuggestedLoadingMore(false);
    }
  }, [profile?.id, completion, shuffleSeed]);

  const loadRecent = useCallback(async () => {
    if (!profile?.id || completion < 30 || recentRequestProfile.current === profile.id) return;
    recentRequestProfile.current = profile.id;
    setRecentLoading(true);
    setRecentError("");
    try {
      const result = await getMatches({
        sort: "newest", per_page: 6, include_filters: 0, shuffle_seed: shuffleSeed,
      });
      setRecentProfiles(Array.isArray(result?.matches) ? result.matches : []);
    } catch (matchError) {
      recentRequestProfile.current = null;
      setRecentError(matchError.message || "Could not load new candidates.");
    } finally {
      setRecentLoading(false);
    }
  }, [profile?.id, completion, shuffleSeed]);

  useEffect(() => {
    const section = recentSection.current;
    if (!section || !profile?.id || completion < 30 || recentRequestProfile.current === profile.id) return undefined;
    const observer = new IntersectionObserver(([entry]) => {
      if (!entry.isIntersecting) return;
      observer.disconnect();
      loadRecent();
    }, { rootMargin: "400px 0px" });
    observer.observe(section);
    return () => observer.disconnect();
  }, [profile?.id, completion, loadRecent]);

  useEffect(() => {
    const section = suggestedSection.current;
    if (!section || !profile?.id || completion < 30 || suggestedRequestProfile.current === profile.id) return undefined;
    const observer = new IntersectionObserver(([entry]) => {
      if (!entry.isIntersecting) return;
      observer.disconnect();
      loadSuggested();
    }, { rootMargin: "400px 0px" });
    observer.observe(section);
    return () => observer.disconnect();
  }, [profile?.id, completion, loadSuggested]);

  useEffect(() => {
    const sentinel = suggestedLoadMoreRef.current;
    if (
      !sentinel
      || !suggestedHasMore.current
      || suggestedLoading
      || suggestedLoadingMore
      || suggestedMoreError
      || completion < 30
    ) return undefined;

    const observer = new IntersectionObserver(([entry]) => {
      if (entry.isIntersecting) loadMoreSuggested();
    }, { rootMargin: "500px 0px" });
    observer.observe(sentinel);
    return () => observer.disconnect();
  }, [completion, loadMoreSuggested, suggested.length, suggestedLoading, suggestedLoadingMore, suggestedMoreError]);

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

  const openCandidateStory = async (match, initialIndex = 0) => {
    setStory({ match, photos: [], activeIndex: initialIndex, loading: true, error: "" });
    try {
      const result = await getMatch(match.id);
      if (!result?.profile) {
        throw new Error("This candidate profile is no longer available.");
      }
      const photos = candidatePhotos(result.profile);
      setStory((current) => current?.match.id === match.id
        ? {
          ...current,
          match: result.profile,
          photos,
          activeIndex: Math.min(initialIndex, Math.max(photos.length - 1, 0)),
          loading: false,
        }
        : current);
    } catch (storyError) {
      setStory((current) => current?.match.id === match.id
        ? { ...current, loading: false, error: storyError.message || "Could not load this candidate's photos." }
        : current);
    }
  };

  const moveStory = useCallback((direction) => {
    setStory((current) => {
      if (!current?.photos.length) return current;
      const count = current.photos.length;
      return {
        ...current,
        activeIndex: (current.activeIndex + direction + count) % count,
        imageError: "",
      };
    });
  }, []);

  const isStoryOpen = Boolean(story);
  useEffect(() => {
    if (!isStoryOpen) return undefined;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    const handleKeyDown = (event) => {
      if (event.key === "Escape") setStory(null);
      if (event.key === "ArrowLeft") moveStory(-1);
      if (event.key === "ArrowRight") moveStory(1);
    };
    window.addEventListener("keydown", handleKeyDown);
    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener("keydown", handleKeyDown);
    };
  }, [isStoryOpen, moveStory]);

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

      {loading && !data ? <DashboardSkeleton /> : error && !data ? (
        <ErrorState message={error} onRetry={load} />
      ) : (
        <>
          {error ? <ErrorState message={error} onRetry={load} /> : null}
          <section className="mm-home-welcome">
            <div>
              <span className="mm-home-kicker">Manomilan</span>
              <h1>{greeting()}, {displayName}</h1>
              <p>Your journey to a meaningful connection starts here.</p>
            </div>
            <span className="mm-home-heart" aria-hidden="true">♡</span>
          </section>

          <section className="mm-home-section mm-recent-section" ref={recentSection}>
            <div className="mm-section-heading">
              <h2>New candidates</h2>
              <Link to="/candidate/matches?sort=newest">View all <ArrowRight size={15} /></Link>
            </div>
            {recentProfiles.length ? (
              <div className="mm-recent-rail">
                {recentProfiles.map((match) => (
                  <button type="button" className="mm-recent-person" key={match.id} onClick={() => openCandidateStory(match)}>
                    <span className="mm-recent-photo">
                      <CandidateImage photos={candidatePhotos(match)} name={text(match.full_name, "Member")} />
                    </span>
                    <strong>{text(match.full_name, "Member").split(" ")[0]}</strong>
                    <small>NEW</small>
                  </button>
                ))}
              </div>
            ) : recentError ? (
              <ErrorState message={recentError} onRetry={loadRecent} />
            ) : recentLoading ? (
              <CardGridSkeleton count={3} />
            ) : completion < 30 ? (
              <div className="mm-recent-loading">Complete your profile to at least 30% to unlock new candidate recommendations.</div>
            ) : (
              <div className="mm-recent-loading">No candidates available right now.</div>
            )}
          </section>

          <section
            className={`mm-home-section ${suggested.length ? "" : "mm-empty-candidates"}`}
            ref={suggestedSection}
          >
            <div className="mm-section-heading">
              <h2>Recommended for you</h2>
              <Link to="/candidate/matches">See all <ArrowRight size={15} /></Link>
            </div>
            {suggested.length ? (
              <div className="mm-recommended-grid">
                {suggested.map((match, index) => (
                  <HomeProfileCard
                    key={match.id}
                    match={match}
                    featured={index === 0}
                    onOpen={openCandidateStory}
                    onInterest={handleInterest}
                    interestBusy={busy[match.id] === "interest"}
                  />
                ))}
              </div>
            ) : suggestedError ? (
              <ErrorState message={suggestedError} onRetry={loadSuggested} />
            ) : suggestedLoading ? (
              <CardGridSkeleton count={3} />
            ) : completion < 30 ? (
              <div className="mm-recent-loading">Complete your profile to at least 30% to unlock personalized recommendations.</div>
            ) : (
              <div className="mm-recent-loading">No candidates available right now.</div>
            )}
            {suggested.length && suggestedHasMore.current ? (
              <div ref={suggestedLoadMoreRef} aria-live="polite">
                {suggestedMoreError ? (
                  <ErrorState message={suggestedMoreError} onRetry={loadMoreSuggested} />
                ) : suggestedLoadingMore ? (
                  <CardGridSkeleton count={3} />
                ) : null}
              </div>
            ) : null}
          </section>

          <div className="cnd-dashboard-legacy">
          <section className="cnd-hero">
            <div className="cnd-hero-copy">
              <div className="cnd-hero-title-row">
                <h2>{profile
                  ? completion >= 100 ? "Welcome to your dashboard" : "Complete your profile"
                  : "Let us complete your profile"}</h2>
                <button
                  type="button"
                  className="cnd-hero-refresh"
                  onClick={() => load(true)}
                  disabled={loading}
                  aria-label="Refresh dashboard data"
                  title="Refresh dashboard data"
                >
                  <RefreshCw size={18} className={loading ? "cnd-spin" : ""} />
                </button>
              </div>
              <p>
                {profile
                  ? completion >= 100
                    ? "Explore compatible matches and manage your interests from here."
                    : "A complete profile receives more genuine interests. Add the remaining details to stand out."
                  : "Add your basic details, partner preferences and photos to unlock compatible matches."}
              </p>
              <div className="cnd-hero-tags">
                <span>{text(profile?.registration_number || profile?.profile_id, "New profile")}</span>
                <span>{humanGender(profile?.gender) || "Gender not set"}</span>
                <span>{text(profile?.city, "Location not added")}</span>
              </div>
            </div>
            {completion < 100 ? (
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
            ) : null}
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

          {completion < 100 ? (
          <section className="cnd-profile-completion-card">
            <div className="cnd-profile-completion-main">
              <ProgressRing value={completion} size={112} stroke={10} label="complete" />
              <div>
                <h3>{completion >= 100 ? "Your profile is complete" : "Complete your profile"}</h3>
                <p>{completion >= 100 ? "Looking great" : "Add the remaining details to improve your matches."}</p>
                <Link to="/candidate/profile" className="cnd-btn cnd-btn-primary cnd-btn-sm">
                  {completion >= 100 ? "View profile" : "Complete profile"} <ArrowRight size={14} />
                </Link>
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
          ) : null}

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
                    <Link to="/candidate/profile?tab=edit" key={item}>
                      <span>{item}</span>
                      <ChevronRight size={17} />
                    </Link>
                  ))}
                </div>
              ) : (
                <div className="cnd-complete-details"><CheckCircle2 size={20} /> All required profile sections are complete.</div>
              )}
            </section>
          ) : null}

          {completion < 100 ? (
          <div className="cnd-dashboard-grid">
            <SectionCard
              title="Your profile"
              subtitle="Keep your details fresh for better matches"
              icon={UserRound}
              action={<Link to="/candidate/profile" className="cnd-text-link">Edit profile <ArrowRight size={14} /></Link>}
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
                  action={<Link to="/candidate/profile" className="cnd-btn cnd-btn-primary">Create profile</Link>}
                />
              )}
            </SectionCard>

          </div>
          ) : null}

          <SectionCard
            title="Recently joined profiles"
            subtitle="Meet members who joined recently"
            icon={Users}
            action={<Link to="/candidate/matches?sort=newest" className="cnd-text-link">View all <ArrowRight size={14} /></Link>}
            className="cnd-suggested cnd-dashboard-recent"
          >
            {recentLoading && !recentProfiles.length ? (
              <CardGridSkeleton count={3} />
            ) : recentError ? (
              <ErrorState message={recentError} onRetry={loadRecent} />
            ) : recentProfiles.length ? (
              <div className="cnd-match-grid cnd-recent-grid">
                {recentProfiles.map((match) => (
                  <MatchResultCard
                    key={`recent-${match.id}`}
                    match={match}
                    onOpen={openProfile}
                    onInterest={handleInterest}
                    interestBusy={busy[match.id] === "interest"}
                  />
                ))}
              </div>
            ) : (
              <EmptyState
                icon={Users}
                title="No recently joined profiles"
                message="New compatible profiles will appear here when available."
              />
            )}
          </SectionCard>

          <SectionCard
            title="Recommended for you"
            subtitle="Ranked using your preferences and profile compatibility"
            icon={Sparkles}
            action={<Link to="/candidate/matches" className="cnd-text-link">See all matches <ArrowRight size={14} /></Link>}
            className="cnd-suggested cnd-dashboard-suggested"
          >
            {suggestedError ? (
              <ErrorState message={suggestedError} onRetry={loadSuggested} />
            ) : suggestedLoading && !suggested.length ? (
              <CardGridSkeleton count={3} />
            ) : suggested.length === 0 ? (
              <EmptyState
                icon={Users}
                title="No suggested matches yet"
                message={profile ? "Try widening your preferences or check back soon." : "Create your profile first so we can find compatible matches."}
                action={<Link to={profile ? "/candidate/matches" : "/candidate/profile"} className="cnd-btn cnd-btn-primary">{profile ? "Browse matches" : "Create profile"}</Link>}
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
              action={<Link to="/candidate/notifications" className="cnd-text-link">View all <ArrowRight size={14} /></Link>}
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
                <Link to="/candidate/profile"><span><UserRound size={19} /></span><div><strong>Edit profile</strong><small>Update your details and photos</small></div></Link>
                <Link to="/candidate/matches"><span><Users size={19} /></span><div><strong>Find matches</strong><small>Search compatible candidates</small></div></Link>
                <Link to="/candidate/interests-sent"><span><Send size={19} /></span><div><strong>Interests sent</strong><small>Track your sent interests</small></div></Link>
                <Link to="/candidate/profile?tab=preferences"><span><Settings2 size={19} /></span><div><strong>Preferences</strong><small>Refine your partner criteria</small></div></Link>
                <Link to="/candidate/profile?tab=documents"><span><CheckCircle2 size={19} /></span><div><strong>Documents</strong><small>Complete verification</small></div></Link>
              </div>
            </SectionCard>
          </div>
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
      {story ? (
        <div className="mm-story-overlay" role="dialog" aria-modal="true" aria-label={`${story.match.full_name || "Candidate"} photos`}>
          <div className="mm-story-viewer">
            <div className="mm-story-progress" aria-hidden="true">
              {story.photos.map((photo, index) => (
                <span key={photo || index} className={index <= story.activeIndex ? "is-active" : ""} />
              ))}
            </div>
            <header className="mm-story-header">
              <span className="mm-story-avatar"><CandidateImage photos={candidatePhotos(story.match)} name={story.match.full_name} size="sm" /></span>
              <div>
                <strong>{text(story.match.full_name, "Candidate")}</strong>
                <small>{[story.match.age ? `${story.match.age} years` : "", story.match.city].filter(Boolean).join(" · ")}</small>
              </div>
              <button type="button" onClick={() => setStory(null)} aria-label="Close photos"><X size={21} /></button>
            </header>
            <div className="mm-story-content">
              {story.loading ? <p>Loading photos...</p> : story.error ? (
                <p className="mm-story-message" role="alert">{story.error}</p>
              ) : story.photos.length ? (
                <img
                  key={story.photos[story.activeIndex]}
                  src={story.photos[story.activeIndex]}
                  alt={`${story.match.full_name || "Candidate"} photo ${story.activeIndex + 1}`}
                  onError={(event) => {
                    event.currentTarget.style.display = "none";
                    setStory((current) => current ? { ...current, imageError: "This photo could not be loaded." } : current);
                  }}
                  onLoad={() => setStory((current) => current?.imageError ? { ...current, imageError: "" } : current)}
                />
              ) : (
                <p className="mm-story-message">No photos have been uploaded for this candidate yet.</p>
              )}
              {story.imageError ? <p className="mm-story-image-error" role="status">{story.imageError}</p> : null}
              {story.photos.length > 1 ? (
                <>
                  <button type="button" className="mm-story-arrow is-previous" onClick={() => moveStory(-1)} aria-label="Previous photo">
                    <ChevronLeft size={28} />
                  </button>
                  <button type="button" className="mm-story-arrow is-next" onClick={() => moveStory(1)} aria-label="Next photo">
                    <ChevronRight size={28} />
                  </button>
                </>
              ) : null}
            </div>
            <Link className="mm-story-profile-link" to={`/candidate/matches/${story.match.id}`}>
              View full profile <ArrowRight size={15} />
            </Link>
          </div>
        </div>
      ) : null}
    </CandidateLayout>
  );
}
