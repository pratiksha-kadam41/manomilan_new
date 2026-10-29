import { useState } from "react";
import { Heart, Check, MapPin, Briefcase, GraduationCap, AlertCircle } from "lucide-react";

export default function MatchCard({ match, onSend }) {
  const [state, setState] = useState("idle");

  const handleClick = async () => {
    if (state === "sending" || state === "sent") return;

    if (!onSend) {
      setState("sent");
      return;
    }

    setState("sending");
    try {
      await onSend(match.id);
      setState("sent");
    } catch {
      setState("error");
    }
  };

  return (
    <div className="cnd-match">
      <div className="cnd-match-avatar">{(match.name || "?").charAt(0)}</div>
      <h3>{match.name}</h3>
      <p className="cnd-match-age">{match.age ? `${match.age} yrs` : "Age not set"}</p>

      <ul>
        <li><MapPin size={15} /> {match.city}</li>
        <li><Briefcase size={15} /> {match.occupation}</li>
        <li><GraduationCap size={15} /> {match.education}</li>
      </ul>

      <button
        className={`cnd-match-btn ${state === "sent" ? "sent" : ""} ${state === "error" ? "error" : ""}`}
        onClick={handleClick}
        disabled={state === "sending" || state === "sent"}
      >
        {state === "sent" && <><Check size={16} /> Interest Sent</>}
        {state === "sending" && <>Sending…</>}
        {state === "error" && <><AlertCircle size={16} /> Try Again</>}
        {state === "idle" && <><Heart size={16} /> Send Interest</>}
      </button>
    </div>
  );
}
