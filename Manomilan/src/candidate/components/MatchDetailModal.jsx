import { useEffect, useState } from "react";
import {
  BadgeCheck, Briefcase, Check, GraduationCap, Heart, Loader2, MapPin,
  Sparkles, UserRound, Users,
} from "lucide-react";
import { getMatch, sendInterest } from "../api";
import { Avatar, EmptyState, ErrorState, InlineNote, Modal, ProgressRing, Skeleton } from "./Ui";
import { displayValue, humanGender, text } from "../utils";

const detailGroups = (profile) => [
  {
    title: "Basic details",
    icon: UserRound,
    rows: [
      ["Gender", humanGender(profile.gender)],
      ["Age", profile.age ? `${profile.age} years` : ""],
      ["Religion", profile.religion],
      ["Community", profile.caste],
      ["Sub community", profile.sub_caste],
      ["Mother tongue", profile.mother_tongue],
      ["Marital status", profile.marital_status],
      ["Height", profile.height],
      ["Location", profile.city],
    ],
  },
  {
    title: "Education and career",
    icon: GraduationCap,
    rows: [
      ["Education", profile.education],
      ["Degree", profile.degree],
      ["College", profile.college],
      ["Profession", profile.profession],
      ["Working field", profile.field_of_working],
      ["Organisation", profile.organisation],
      ["Designation", profile.designation],
      ["Annual income", profile.annual_income],
    ],
  },
  {
    title: "Family",
    icon: Users,
    rows: [
      ["Father", profile.family?.father_name],
      ["Mother", profile.family?.mother_name],
      ["Brothers", profile.family?.brothers],
      ["Sisters", profile.family?.sisters],
      ["Family values", profile.family?.family_values],
      ["Financial status", profile.family?.financial_status],
    ],
  },
  {
    title: "Lifestyle",
    icon: Sparkles,
    rows: [
      ["Diet", profile.diet],
      ["Smoking", profile.smoking],
      ["Drinking", profile.drinking],
      ["Hobbies", profile.hobbies],
    ],
  },
];

export default function MatchDetailModal({
  open, profileId, initialMatch, onClose, onUpdated, allowInterest = true,
}) {
  const [profile, setProfile] = useState(initialMatch || null);
  const [loading, setLoading] = useState(Boolean(open));
  const [error, setError] = useState("");
  const [actionError, setActionError] = useState("");
  const [interestBusy, setInterestBusy] = useState(false);

  useEffect(() => {
    if (!open || !profileId) return undefined;
    let active = true;

    getMatch(profileId)
      .then((result) => {
        if (active && result?.profile) {
          setProfile(result.profile);
          onUpdated?.(result.profile);
        }
      })
      .catch((loadError) => {
        if (active) setError(loadError.message || "Could not load this profile.");
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, profileId]);

  const handleInterest = async () => {
    if (!profile) return;
    setInterestBusy(true);
    setActionError("");
    try {
      const result = await sendInterest(profile.id);
      const updated = {
        ...profile,
        interest_id: result?.interest?.id,
      };
      setProfile(updated);
      onUpdated?.(updated);
    } catch (sendError) {
      setActionError(sendError.message || "Could not send the interest.");
      if (sendError.status === 409) {
        setProfile((current) => current ? { ...current, interest_id: current.interest_id || -1 } : current);
      }
    } finally {
      setInterestBusy(false);
    }
  };

  const name = text(profile?.full_name, "Member profile");
  const interestDone = Boolean(profile?.interest_id);

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={loading && !profile ? "Loading profile" : name}
      eyebrow={profile ? [profile.age ? `${profile.age} years` : "", humanGender(profile.gender), profile.city].filter(Boolean).join(" • ") : "Matrimonial profile"}
      size="lg"
      footer={profile && allowInterest ? (
        <div className="cnd-modal-actions">
          <button
            type="button"
            className="cnd-btn cnd-btn-primary"
            onClick={handleInterest}
            disabled={interestDone || interestBusy}
          >
            {interestBusy ? <Loader2 size={16} className="cnd-spin" /> : interestDone ? <Check size={16} /> : <Heart size={16} />}
            {interestDone ? "Interest sent" : "Send interest"}
          </button>
        </div>
      ) : null}
    >
      {error ? (
        <ErrorState message={error} onRetry={() => { setError(""); setLoading(true); getMatch(profileId).then((r) => { setProfile(r.profile); setLoading(false); }).catch((e) => { setError(e.message); setLoading(false); }); }} />
      ) : loading && !profile ? (
        <div className="cnd-modal-loading">
          <Skeleton className="cnd-skeleton-modal-photo" />
          <Skeleton lines={4} />
        </div>
      ) : !profile ? (
        <EmptyState icon={UserRound} title="Profile unavailable" message="This profile could not be displayed right now." />
      ) : (
        <div className="cnd-profile-modal">
          <div className="cnd-pm-hero">
            <div className="cnd-pm-photo">
              {profile.photo_url ? (
                <img src={profile.photo_url} alt={`${name} profile`} loading="lazy" decoding="async" />
              ) : (
                <Avatar name={name} size="xl" />
              )}
            </div>
            <div className="cnd-pm-intro">
              <div className="cnd-pm-name">
                <h3>{name}</h3>
                {profile.verified ? (
                  <span className="cnd-verified-inline"><BadgeCheck size={16} /> Verified</span>
                ) : null}
              </div>
              <p>
                {[profile.profession, profile.organisation].filter(Boolean).join(" at ") || "Profile details added"}
              </p>
              <div className="cnd-pm-tags">
                {profile.city ? <span><MapPin size={14} /> {profile.city}</span> : null}
                {profile.education ? <span><GraduationCap size={14} /> {profile.education}</span> : null}
                {profile.profession ? <span><Briefcase size={14} /> {profile.profession}</span> : null}
              </div>
            </div>
            <div className="cnd-pm-score">
              <ProgressRing value={profile.match_percentage} size={88} stroke={8} />
              <small>Match score</small>
            </div>
          </div>

          {profile.match_reasons?.length ? (
            <div className="cnd-match-reasons cnd-pm-reasons">
              {profile.match_reasons.map((reason) => <span key={reason}>{reason}</span>)}
            </div>
          ) : null}

          {actionError ? <InlineNote tone="error">{actionError}</InlineNote> : null}

          {profile.about ? (
            <section className="cnd-pm-section">
              <h4>About</h4>
              <p className="cnd-pm-about">{profile.about}</p>
            </section>
          ) : null}

          {detailGroups(profile).map((group) => {
            const Icon = group.icon;
            const rows = group.rows.filter(([, value]) => text(value));
            if (!rows.length) return null;
            return (
              <section key={group.title} className="cnd-pm-section">
                <h4><Icon size={16} /> {group.title}</h4>
                <div className="cnd-pm-grid">
                  {rows.map(([label, value]) => (
                    <div key={label}>
                      <span>{label}</span>
                      <strong>{displayValue(value)}</strong>
                    </div>
                  ))}
                </div>
              </section>
            );
          })}
        </div>
      )}
    </Modal>
  );
}
