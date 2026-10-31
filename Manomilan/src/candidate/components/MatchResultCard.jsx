import {
  BadgeCheck, Briefcase, Check, GraduationCap, Heart, Loader2, MapPin,
} from "lucide-react";
import { Avatar } from "./Ui";
import { humanGender, text } from "../utils";

export default function MatchResultCard({
  match,
  onOpen,
  onInterest,
  interestBusy = false,
  detailed = false,
}) {
  const interestDone = Boolean(match.interest_id);
  const interestDisabled = interestDone || interestBusy;
  const name = text(match.full_name, "Member");
  const age = match.age ? `${match.age} yrs` : "";
  const gender = humanGender(match.gender);

  return (
    <article className="cnd-match-card">
      <button type="button" className="cnd-match-open" onClick={() => onOpen?.(match)} aria-label={`View ${name}'s profile`}>
        <span className="cnd-match-photo">
          {match.photo_url ? (
            <img src={match.photo_url} alt={`${name} profile`} loading="lazy" decoding="async" />
          ) : (
            <Avatar name={name} size="xl" />
          )}
          {match.verified ? (
            <span className="cnd-verified" title="Verified profile"><BadgeCheck size={16} /> Verified</span>
          ) : null}
        </span>

        <span className="cnd-match-body">
          <span className="cnd-match-title-row">
            <strong>{name}</strong>
            {detailed && match.match_percentage != null ? (
              <span className="find-match-score">{Math.round(Number(match.match_percentage))}% match</span>
            ) : null}
          </span>
          {detailed && (match.registration_number || match.profile_id) ? (
            <span className="find-match-id">ID: {match.registration_number || match.profile_id}</span>
          ) : null}
          <span className="cnd-match-sub">
            {[age, gender, match.city].filter(Boolean).join(" • ") || "Profile details added"}
          </span>
          <span className="cnd-match-facts">
            {match.profession ? <span><Briefcase size={14} /> {match.profession}</span> : null}
            {match.education ? <span><GraduationCap size={14} /> {match.education}</span> : null}
            {match.city ? <span><MapPin size={14} /> {match.city}</span> : null}
          </span>
          {match.match_reasons?.length ? (
            <span className="cnd-match-reasons">
              {match.match_reasons.slice(0, 2).map((reason) => (
                <span key={reason}>{reason}</span>
              ))}
            </span>
          ) : null}
          {detailed && match.about ? (
            <span className="find-match-about">{match.about}</span>
          ) : null}
        </span>
      </button>

      <footer className="cnd-match-actions">
        <button
          type="button"
          className={`cnd-btn cnd-btn-primary cnd-match-interest ${interestDone ? "done" : ""}`}
          onClick={() => onInterest?.(match)}
          disabled={interestDisabled}
        >
          {interestBusy ? <Loader2 size={16} className="cnd-spin" /> : interestDone ? <Check size={16} /> : <Heart size={16} />}
          {interestDone ? "Interest sent" : "Send interest"}
        </button>
      </footer>
    </article>
  );
}
