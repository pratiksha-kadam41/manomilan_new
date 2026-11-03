import { useEffect, useRef, useState } from "react";
import { GraduationCap, Handshake, Users } from "lucide-react";
import { apiMediaUrl, request } from "../api";
import "./WorkshopRegistration.css";

const STEPS = ["Details", "Payment", "Confirm"];
const MAX_SCREENSHOT_SIZE = 4 * 1024 * 1024;
const TRANSACTION_ID_PATTERN = /^[A-Za-z0-9]{12,22}$/;
const TRANSACTION_ID_ERROR = "Enter 12 to 22 letters or numbers only. Spaces and special characters are not allowed.";
const today = new Date().toISOString().slice(0, 10);

export default function WorkshopRegistration() {
  const formRef = useRef(null);
  const [step, setStep] = useState(1);
  const [errors, setErrors] = useState({});
  const [submitError, setSubmitError] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [consent, setConsent] = useState(false);
  const [workshops, setWorkshops] = useState([]);
  const [selectedWorkshopId, setSelectedWorkshopId] = useState("");
  const [workshopsLoading, setWorkshopsLoading] = useState(true);
  const [workshopsError, setWorkshopsError] = useState("");
  const selectedWorkshop = workshops.find((workshop) => String(workshop.id) === selectedWorkshopId);

  useEffect(() => {
    let cancelled = false;
    const loadWorkshops = async () => {
      setWorkshopsLoading(true);
      setWorkshopsError("");
      try {
        const response = await request("/workshop-events/active", { auth: false });
        if (cancelled) return;
        const activeWorkshops = Array.isArray(response?.data) ? response.data : [];
        setWorkshops(activeWorkshops);
        setSelectedWorkshopId(activeWorkshops.length ? String(activeWorkshops[0].id) : "");
      } catch (error) {
        if (!cancelled) {
          setWorkshopsError(error.message || "Unable to load current workshop details.");
        }
      } finally {
        if (!cancelled) setWorkshopsLoading(false);
      }
    };
    loadWorkshops();
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    if (!submitted) return undefined;
    const redirectTimer = window.setTimeout(() => {
      window.location.assign("/");
    }, 3000);
    return () => window.clearTimeout(redirectTimer);
  }, [submitted]);

  const clearError = (name) => {
    setErrors((current) => {
      if (!current[name]) return current;
      const next = { ...current };
      delete next[name];
      return next;
    });
    setSubmitError("");
  };

  const validateStep = (stepNumber) => {
    const form = formRef.current;
    if (!form) return false;

    const data = new FormData(form);
    const nextErrors = {};
    const requiredFields = stepNumber === 1
      ? [
          "full_name", "mobile_number", "whatsapp_number", "date_of_birth",
          "education", "marital_status", "city", "district", "parent_name",
          "parent_contact_number", "workshop_event_id",
        ]
      : ["payment_screenshot", "transaction_id"];

    requiredFields.forEach((name) => {
      const value = data.get(name);
      const isEmpty = value instanceof File
        ? value.size === 0
        : typeof value !== "string" || !value.trim();
      if (isEmpty) {
        nextErrors[name] = "This field is required.";
      }
    });

    if (stepNumber === 1) {
      ["mobile_number", "whatsapp_number", "parent_contact_number"].forEach((name) => {
        const value = String(data.get(name) || "");
        if (value && !/^\d{10}$/.test(value)) {
          nextErrors[name] = "Enter exactly 10 digits.";
        }
      });

      const birthDate = String(data.get("date_of_birth") || "");
      if (birthDate && birthDate >= today) {
        nextErrors.date_of_birth = "Date of birth must be in the past.";
      }
    }

    if (stepNumber === 2) {
      const screenshot = data.get("payment_screenshot");
      if (screenshot instanceof File && screenshot.size > 0) {
        if (!screenshot.type.startsWith("image/")) {
          nextErrors.payment_screenshot = "Upload an image file.";
        } else if (screenshot.size > MAX_SCREENSHOT_SIZE) {
          nextErrors.payment_screenshot = "Image must be 4 MB or smaller.";
        }
      }

      const transactionId = String(data.get("transaction_id") || "");
      if (transactionId && !TRANSACTION_ID_PATTERN.test(transactionId)) {
        nextErrors.transaction_id = TRANSACTION_ID_ERROR;
      }
    }

    setErrors(nextErrors);
    const firstInvalid = Object.keys(nextErrors)[0];
    if (firstInvalid) {
      form.elements.namedItem(firstInvalid)?.focus();
      return false;
    }
    return true;
  };

  const moveToStep = (nextStep) => {
    setStep(nextStep);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  const handlePhoneInput = (event) => {
    event.currentTarget.value = event.currentTarget.value.replace(/\D/g, "").slice(0, 10);
    clearError(event.currentTarget.name);
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    if (!validateStep(2) || !consent) {
      if (!consent) setErrors({ consent: "Please confirm the declaration to continue." });
      return;
    }

    setSubmitting(true);
    setSubmitError("");
    try {
      await request("/workshop-registrations", {
        method: "POST",
        body: new FormData(formRef.current),
        auth: false,
      });
      setSubmitted(true);
    } catch (error) {
      setSubmitError(error.message || "Unable to submit your registration. Please try again.");
      if (error.errors && typeof error.errors === "object") {
        const validationErrors = Object.fromEntries(
          Object.entries(error.errors).map(([name, messages]) => [
            name,
            Array.isArray(messages) ? messages[0] : String(messages),
          ]),
        );
        setErrors((current) => ({ ...current, ...validationErrors }));
        if (Object.keys(validationErrors).some((name) => [
          "full_name", "mobile_number", "whatsapp_number", "date_of_birth",
          "education", "marital_status", "city", "district", "parent_name",
          "parent_contact_number", "workshop_event_id",
        ].includes(name))) {
          setStep(1);
        } else if (Object.keys(validationErrors).some((name) => [
          "payment_screenshot", "transaction_id",
        ].includes(name))) {
          setStep(2);
        }
      }
    } finally {
      setSubmitting(false);
    }
  };

  const fieldError = (name) => errors[name]
    ? <span className="workshop-error">{errors[name]}</span>
    : null;

  const formatFee = (fee, currency) => {
    try {
      return new Intl.NumberFormat("en-IN", {
        style: "currency",
        currency: currency || "INR",
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
      }).format(Number(fee || 0));
    } catch {
      return `${currency || "INR"} ${fee || 0}`;
    }
  };

  const formatWorkshopDate = (value) => {
    if (!value) return "";
    const date = new Date(`${String(value).slice(0, 10)}T00:00:00`);
    return Number.isNaN(date.getTime())
      ? ""
      : date.toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" });
  };

  if (submitted) {
    return (
      <main className="workshop-page">
        <section className="workshop-card workshop-success" role="status">
          <img src="/website/Mano_Milan_Final_Website.png" alt="Aaryans Manomilan" />
          <div className="workshop-success-icon" aria-hidden="true">✓</div>
          <h1>Workshop registration Successful</h1>
          <p>Thank you for registering. Our team will verify your payment and contact you shortly.</p>
          <p className="workshop-redirect-note">Returning you to the home page…</p>
          <a className="workshop-primary" href="/">Back to Home</a>
        </section>
      </main>
    );
  }

  return (
    <main className="workshop-page">
      <section className="workshop-card">
        <header className="workshop-brand-header">
          <a className="workshop-logo" href="/" aria-label="Aaryans Manomilan home">
            <img src="/website/Mano_Milan_Final_Website.png" alt="Aaryans Manomilan" />
          </a>
          <p className="workshop-tagline">येथे मनं मिळतात... नाती जुळतात</p>
          <div className="workshop-heading">
            <span className="workshop-kicker">Aaryans Manomilan <i /> Workshop</span>
            <h1>Workshop Registration</h1>
            <p>Your next step starts here. Complete the details below to reserve your place.</p>
          </div>
        </header>

        {workshopsLoading || workshopsError || workshops.length === 0 ? (
          <div className="workshop-availability" role={workshopsError ? "alert" : "status"}>
            {workshopsLoading ? <p>Loading available workshops…</p> : null}
            {workshopsError ? (
              <>
                <h2>Workshop details are unavailable</h2>
                <p>{workshopsError}</p>
                <button type="button" className="workshop-secondary" onClick={() => window.location.reload()}>Try again</button>
              </>
            ) : null}
            {!workshopsLoading && !workshopsError && workshops.length === 0 ? (
              <h2>No active Workshop</h2>
            ) : null}
          </div>
        ) : (
        <div className="workshop-layout">
          <div className="workshop-main">
            <section className="workshop-event-choice" aria-label="Selected workshop">
              <span className="workshop-event-kicker">Your workshop</span>
              {workshops.length > 1 ? (
                <label className="workshop-field">
                  Select a workshop <b>*</b>
                  <select
                    value={selectedWorkshopId}
                    onChange={(event) => {
                      setSelectedWorkshopId(event.target.value);
                      clearError("workshop_event_id");
                    }}
                  >
                    {workshops.map((workshop) => (
                      <option key={workshop.id} value={workshop.id}>
                        {workshop.title} — {formatFee(workshop.registration_fee, workshop.currency)}
                      </option>
                    ))}
                  </select>
                  {fieldError("workshop_event_id")}
                </label>
              ) : null}
              {selectedWorkshop ? (
                <>
                  {workshops.length === 1 ? <h2>{selectedWorkshop.title}</h2> : null}
                  {selectedWorkshop.description ? <p>{selectedWorkshop.description}</p> : null}
                  {selectedWorkshop.start_date ? (
                    <small>
                      {formatWorkshopDate(selectedWorkshop.start_date)}
                      {selectedWorkshop.end_date && selectedWorkshop.end_date !== selectedWorkshop.start_date
                        ? ` – ${formatWorkshopDate(selectedWorkshop.end_date)}`
                        : ""}
                    </small>
                  ) : null}
                </>
              ) : null}
            </section>
            <ol className="workshop-steps" aria-label="Registration progress">
              {STEPS.map((label, index) => {
                const number = index + 1;
                const state = number < step ? "done" : number === step ? "active" : "";
                return (
                  <li className={state} key={label} aria-current={number === step ? "step" : undefined}>
                    <span className="workshop-step-dot">{number < step ? "✓" : number}</span>
                    <span className="workshop-step-label">{label}</span>
                  </li>
                );
              })}
            </ol>

            {submitError && <div className="workshop-alert" role="alert">{submitError}</div>}

            <form ref={formRef} onSubmit={handleSubmit} noValidate>
          <input type="hidden" name="workshop_event_id" value={selectedWorkshopId} />
          <section className={`workshop-panel${step === 1 ? " active" : ""}`} aria-hidden={step !== 1}>
            <h2 className="workshop-section-title">Personal Details</h2>
            <div className="workshop-grid">
              <label className="workshop-field workshop-span-2">
                Your Name <b>*</b>
                <input name="full_name" type="text" maxLength="150" autoComplete="name" required
                  className={errors.full_name ? "invalid" : ""} onChange={() => clearError("full_name")} />
                {fieldError("full_name")}
              </label>
              <label className="workshop-field">
                Mobile Number <b>*</b>
                <input name="mobile_number" type="tel" inputMode="numeric" maxLength="10" autoComplete="tel"
                  required className={errors.mobile_number ? "invalid" : ""}
                  onInput={handlePhoneInput} />
                {fieldError("mobile_number")}
              </label>
              <label className="workshop-field">
                WhatsApp Number <b>*</b>
                <input name="whatsapp_number" type="tel" inputMode="numeric" maxLength="10"
                  required className={errors.whatsapp_number ? "invalid" : ""}
                  onInput={handlePhoneInput} />
                {fieldError("whatsapp_number")}
              </label>
              <label className="workshop-field">
                Date of Birth <b>*</b>
                <input name="date_of_birth" type="date" max={today} required
                  className={errors.date_of_birth ? "invalid" : ""}
                  onChange={() => clearError("date_of_birth")} />
                {fieldError("date_of_birth")}
              </label>
              <label className="workshop-field">
                Education <b>*</b>
                <input name="education" type="text" maxLength="150" placeholder="e.g. B.Com, B.E., M.A."
                  required className={errors.education ? "invalid" : ""}
                  onChange={() => clearError("education")} />
                {fieldError("education")}
              </label>
              <label className="workshop-field">
                Marital Status <b>*</b>
                <select name="marital_status" defaultValue="" required
                  className={errors.marital_status ? "invalid" : ""}
                  onChange={() => clearError("marital_status")}>
                  <option value="" disabled>Select Marital Status</option>
                  <option value="Unmarried">Unmarried</option>
                  <option value="Divorced">Divorced</option>
                  <option value="Widow">Widow</option>
                  <option value="Widower">Widower</option>
                </select>
                {fieldError("marital_status")}
              </label>
              <label className="workshop-field">
                City <b>*</b>
                <input name="city" type="text" maxLength="100" required
                  className={errors.city ? "invalid" : ""} onChange={() => clearError("city")} />
                {fieldError("city")}
              </label>
              <label className="workshop-field">
                District <b>*</b>
                <input name="district" type="text" maxLength="100" required
                  className={errors.district ? "invalid" : ""} onChange={() => clearError("district")} />
                {fieldError("district")}
              </label>
            </div>

            <h2 className="workshop-section-title">Parent / Guardian Details</h2>
            <div className="workshop-grid">
              <label className="workshop-field workshop-span-2">
                Parent Name <b>*</b>
                <input name="parent_name" type="text" maxLength="150" required
                  className={errors.parent_name ? "invalid" : ""}
                  onChange={() => clearError("parent_name")} />
                {fieldError("parent_name")}
              </label>
              <label className="workshop-field workshop-span-2">
                Parent Contact Number <b>*</b>
                <input name="parent_contact_number" type="tel" inputMode="numeric" maxLength="10"
                  required className={errors.parent_contact_number ? "invalid" : ""}
                  onInput={handlePhoneInput} />
                {fieldError("parent_contact_number")}
              </label>
            </div>
            <button type="button" className="workshop-primary workshop-full" onClick={() => {
              if (validateStep(1)) moveToStep(2);
            }}>
              Next <span aria-hidden="true">→</span>
            </button>
          </section>

          <section className={`workshop-panel${step === 2 ? " active" : ""}`} aria-hidden={step !== 2}>
            <h2 className="workshop-section-title">Payment Details</h2>
            <div className="workshop-payment-box">
              <span className="workshop-payment-kicker">Registration payment</span>
              <strong>{selectedWorkshop?.title ? `Scan to pay for ${selectedWorkshop.title}` : "Scan the QR code to pay"}</strong>
              <div className="workshop-qr-frame">
                <img src={apiMediaUrl(selectedWorkshop?.qr_image_url)} alt="Workshop payment QR code" />
              </div>
              <span className="workshop-fee">{formatFee(selectedWorkshop?.registration_fee, selectedWorkshop?.currency)}</span>
              <small>One-time workshop registration fee</small>
              <span className="workshop-payment-note">
                <span aria-hidden="true">✓</span> Keep your payment screenshot and UTR number ready
              </span>
            </div>
            <label className="workshop-field workshop-field-spaced">
              Upload Payment Screenshot <b>*</b>
              <input name="payment_screenshot" type="file" accept="image/jpeg,image/png" required
                className={errors.payment_screenshot ? "invalid" : ""}
                onChange={() => clearError("payment_screenshot")} />
              <small>JPG or PNG, up to 4 MB.</small>
              {fieldError("payment_screenshot")}
            </label>
            <label className="workshop-field workshop-field-spaced">
              Transaction ID / UTR Number <b>*</b>
              <input name="transaction_id" type="text" maxLength="22" pattern="^[A-Za-z0-9]{12,22}$"
                placeholder="Enter your transaction ID / UTR number" required
                className={errors.transaction_id ? "invalid" : ""}
                onChange={() => clearError("transaction_id")} />
              {fieldError("transaction_id")}
            </label>
            <div className="workshop-actions">
              <button type="button" className="workshop-secondary" onClick={() => moveToStep(1)}>← Back</button>
              <button type="button" className="workshop-primary" onClick={() => {
                if (validateStep(2)) moveToStep(3);
              }}>
                Next <span aria-hidden="true">→</span>
              </button>
            </div>
          </section>

          <section className={`workshop-panel${step === 3 ? " active" : ""}`} aria-hidden={step !== 3}>
            <h2 className="workshop-section-title">Declaration</h2>
            <div className="workshop-declaration">
              <label>
                <input name="consent" type="checkbox" value="1" checked={consent}
                  onChange={(event) => {
                    setConsent(event.target.checked);
                    clearError("consent");
                  }} />
                <span>
                  I confirm that the information provided is <strong>true and correct</strong>.
                  I understand that registration is considered complete only after
                  <strong> successful payment verification</strong>.
                </span>
              </label>
              {fieldError("consent")}
            </div>
            <div className="workshop-actions">
              <button type="button" className="workshop-secondary" onClick={() => moveToStep(2)}>← Back</button>
              <button type="submit" className="workshop-primary" disabled={!consent || submitting}>
                {submitting ? "Submitting…" : "Register For Workshop"}
              </button>
            </div>
          </section>
            </form>
          </div>

          <aside className="workshop-journey" aria-label="Workshop benefits">
            <span className="workshop-journey-ornament" aria-hidden="true">✦</span>
            <h2>Your workshop journey</h2>
            <p className="workshop-journey-intro">A meaningful step towards finding your life partner.</p>
            <ul>
              <li>
                <span className="workshop-journey-icon"><Users size={19} aria-hidden="true" /></span>
                <span><strong>Meet Like-Minded People</strong><small>Connect with genuine and serious candidates.</small></span>
              </li>
              <li>
                <span className="workshop-journey-icon"><GraduationCap size={19} aria-hidden="true" /></span>
                <span><strong>Gain Expert Guidance</strong><small>Get valuable insights on relationships and marriage.</small></span>
              </li>
              <li>
                <span className="workshop-journey-icon"><Handshake size={19} aria-hidden="true" /></span>
                <span><strong>Build Meaningful Connections</strong><small>Participate in interactive sessions and curated meets.</small></span>
              </li>
            </ul>
            <div className="workshop-journey-art" aria-hidden="true">
              <span className="workshop-art-spark">✦</span>
              <span className="workshop-art-heart">♡</span>
              <span className="workshop-art-couple"><i /><i /></span>
              <span className="workshop-art-leaf workshop-art-leaf-left">❧</span>
              <span className="workshop-art-leaf workshop-art-leaf-right">❧</span>
            </div>
          </aside>
        </div>
        )}
      </section>
    </main>
  );
}
