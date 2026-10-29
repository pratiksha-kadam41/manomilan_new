import { useState } from "react";

// Floating "Upcoming Events" button + poster popup (Bootstrap modal markup).
export default function UpcomingEvents() {
  const [open, setOpen] = useState(false);

  return (
    <>
      <div className="event-btn" onClick={() => setOpen(true)}>
        <span>📢</span> Upcoming Events
      </div>

      {open && (
        <div className="modal fade show d-block poster-modal"
             onClick={(e) => e.target === e.currentTarget && setOpen(false)}>
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content">
              <span className="poster-close" onClick={() => setOpen(false)}>&times;</span>
              <img src="/website/upcoming.jpeg" alt="Event Poster" />
            </div>
          </div>
        </div>
      )}
    </>
  );
}