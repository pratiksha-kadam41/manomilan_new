// src/pages/ProfileDetail.jsx  (page + backend calls in one file)
// npm install react-icons react-router-dom
import { useCallback, useEffect, useRef, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { apiUrl, getToken } from "../api";
import AdminLayout from "../admin/components/AdminLayout";
import BioDataModal from "../admin/pages/Profiles/BioDataModal.jsx";
import {
  FaAddressBook, FaArrowLeft, FaBriefcase, FaCheck, FaCheckCircle, FaChevronLeft, FaChevronRight,
  FaClock, FaCommentDots, FaCompress, FaDownload, FaEdit, FaEnvelope, FaExpand, FaEye, FaFileAlt,
  FaGraduationCap, FaHeartbeat, FaHome, FaIdCard, FaCalendarAlt, FaImages, FaInfoCircle, FaListUl,
  FaMapMarkerAlt, FaMinus, FaPause, FaPhoneAlt, FaPlay, FaRedo, FaShieldAlt, FaStar, FaTimes,
  FaTrash, FaUpload, FaUser, FaUsers,
} from "react-icons/fa";
import "./ProfileDetail.css";

/* =====================================================================
   0. BACKEND CALLS + VALIDATION
   ===================================================================== */
// Set these in .env:  VITE_API_URL=https://aaryansmanomilan.com   VITE_ASSET_URL=https://aaryansmanomilan.com

const API_BASE = apiUrl.replace(/\/api\/?$/, "");
const ASSET_BASE = (import.meta.env.VITE_ASSET_URL || API_BASE).replace(/\/+$/, "");

class ApiError extends Error {
  constructor(message, status = 0) {
    super(message);
    this.name = "ApiError";
    this.status = status;
  }
}

/* ---------------- validation / safety helpers ---------------- */

// Only positive whole numbers are allowed as an id (blocks "abc", "-1", "1;DROP", etc.)
const isValidId = (id) => /^\d{1,12}$/.test(String(id ?? "")) && Number(id) > 0;

// Turns "uploads/x.png" into a full URL
const assetUrl = (path) => {
  if (!path || typeof path !== "string") return "";
  if (/^https?:\/\//i.test(path)) return path;
  return `${ASSET_BASE}/${path.replace(/^\/+/, "")}`;
};

/* ---------------- request wrapper ---------------- */

const authHeaders = () => {
  try {
    const token = localStorage.getItem("token"); // change if your admin token is stored elsewhere
    return token ? { Authorization: `Bearer ${token}` } : {};
  } catch {
    return {};
  }
};

const FALLBACK_MESSAGES = {
  401: "Your session has expired. Please log in again.",
  403: "You do not have permission to do this.",
  404: "Profile not found.",
  429: "Too many requests. Please wait a moment and try again.",
};

async function request(path, { method = "GET", signal, expect = "json", body = null } = {}) {
  const isForm = typeof FormData !== "undefined" && body instanceof FormData;
  const headers = {
    Accept: expect === "json" ? "application/json" : "*/*",
    ...authHeaders(),
    ...(getToken() ? { Authorization: `Bearer ${getToken()}` } : {}),
  };
  if (body && !isForm) headers["Content-Type"] = "application/json";

  let res;
  try {
    res = await fetch(`${API_BASE}${path}`, {
      method,
      signal,
      headers,
      body: isForm ? body : body ? JSON.stringify(body) : undefined,
    });
  } catch (err) {
    if (err.name === "AbortError") throw err;
    throw new ApiError("Cannot reach the server. Check your internet connection and try again.");
  }

  if (!res.ok) {
    let serverMessage = "";
    let fieldError = "";
    try {
      const payload = await res.json();
      serverMessage = payload?.message || "";
      const first = Object.values(payload?.errors || {}).flat().filter(Boolean)[0];
      fieldError = typeof first === "string" ? first : "";
    } catch {
      /* response was not JSON */
    }
    let message;
    if (res.status >= 500) message = "Server error. Please try again in a moment.";
    else if (res.status === 401) message = FALLBACK_MESSAGES[401];
    else message = fieldError || serverMessage || FALLBACK_MESSAGES[res.status] || `Request failed (${res.status}).`;
    throw new ApiError(message, res.status);
  }

  if (expect === "blob") return res;
  try {
    return await res.json();
  } catch {
    throw new ApiError("The server sent an invalid response.", res.status);
  }
}

/* ---------------- public API ---------------- */

function normalizeProfile(p) {
  const docs = Array.isArray(p.documents) ? p.documents : [];
  const photos = (Array.isArray(p.photos) ? p.photos : [])
    .filter((photo) => photo && typeof photo === "object")
    .map((photo, i) => ({
      id: photo.id ?? `photo-${i}`,
      label: String(photo.label || photo.name || `Photo ${i + 1}`),
      url: assetUrl(photo.url || photo.path || ""),
    }))
    .filter((photo) => photo.url);

  return {
    ...p,
    _photo: assetUrl(p.profile_photo || p.photo || ""),
    photos,
    documents: docs
      .filter((d) => d && typeof d === "object")
      .map((d, i) => ({
        id: d.id ?? i,
        label: String(d.label || d.type || d.name || "Document"),
        url: d.url || d.file_path || d.path || "",
      })),
  };
}

async function getProfile(id, signal) {
  if (!isValidId(id)) throw new ApiError("This profile link is not valid.", 400);
  const json = await request(`/api/profiles/${id}`, { signal });
  const profile = json?.data ?? json;
  if (!profile || typeof profile !== "object" || Array.isArray(profile)) {
    throw new ApiError("The server sent an invalid response.");
  }
  return normalizeProfile(profile);
}

async function updateProfile(profileId, values, documentFiles) {
  if (!isValidId(profileId)) throw new ApiError("Invalid profile.", 400);

  const form = new FormData();
  Object.entries(values).forEach(([key, value]) => {
    if (value === undefined) return;
    form.append(key, value === null ? "" : String(value));
  });
  Object.entries(documentFiles || {}).forEach(([key, file]) => {
    if (file) form.append(`documents[${key}]`, file);
  });

  const json = await request(`/api/profiles/${profileId}/update`, { method: "POST", body: form });
  const profile = json?.data ?? json;
  if (!profile || typeof profile !== "object" || Array.isArray(profile)) {
    throw new ApiError("The server sent an invalid response.");
  }
  return normalizeProfile(profile);
}

async function deleteDocument(profileId, docId) {
  if (!isValidId(profileId) || !/^[\w-]{1,64}$/.test(String(docId))) {
    throw new ApiError("Invalid document.", 400);
  }
  await request(`/api/profiles/${profileId}/documents/${docId}`, { method: "DELETE" });
}

async function getDocumentFile(profileId, docId) {
  if (!isValidId(profileId) || !/^[\w-]{1,64}$/.test(String(docId))) {
    throw new ApiError("Invalid document.", 400);
  }
  const res = await request(`/api/profiles/${profileId}/documents/${encodeURIComponent(docId)}/file`, { expect: "blob" });
  const blob = await res.blob();
  if (!blob.size) throw new ApiError("The document is empty or unavailable.");
  return blob;
}

async function getBiodataPreview(profileId) {
  if (!isValidId(profileId)) throw new ApiError("Invalid profile.", 400);
  const json = await request(`/api/profiles/${profileId}/biodata-preview`);
  if (!json?.data || typeof json.data !== "object") {
    throw new ApiError("The server sent an invalid BioData response.");
  }
  return json.data;
}

/* =====================================================================
   1. HELPERS
   ===================================================================== */
const NA = "N/A";

// read "a.b.c" paths safely
const pick = (obj, path) => String(path).split(".").reduce((o, k) => (o == null ? undefined : o[k]), obj);

const parseDate = (v) => {
  if (!v) return null;
  const s = String(v).trim();
  const m = /^(\d{2})-(\d{2})-(\d{4})$/.exec(s); // 29-01-1997
  const d = m ? new Date(+m[3], +m[2] - 1, +m[1]) : new Date(s);
  return Number.isNaN(d.getTime()) ? null : d;
};
const ageFrom = (d) => {
  const t = new Date();
  let a = t.getFullYear() - d.getFullYear();
  if (t.getMonth() < d.getMonth() || (t.getMonth() === d.getMonth() && t.getDate() < d.getDate())) a--;
  return a >= 0 && a < 120 ? a : null;
};
const longDate = (d) => `${d.getDate()} ${d.toLocaleString("en-GB", { month: "short" })} ${d.getFullYear()}`;

// format a raw backend value for display; returns null when empty
const fmt = (v, type) => {
  if (v === null || v === undefined || (typeof v === "object" && !Array.isArray(v))) return null;
  if (Array.isArray(v)) v = v.filter(Boolean).join(", ");
  const s = String(v).trim();
  if (!s) return null;
  if (type === "money") {
    const n = Number(s.replace(/,/g, ""));
    return Number.isFinite(n) ? `₹ ${n.toLocaleString("en-IN")}` : s;
  }
  if (type === "date") {
    const d = parseDate(s);
    return d ? longDate(d) : s;
  }
  return s;
};

// is the profile verified?  (reads is_verified / verified / verification_status)
const isVerified = (p) => {
  const v = p.is_verified ?? p.verified ?? p.verification_status;
  if (typeof v === "string") return ["1", "true", "yes", "verified", "approved", "approve", "complete", "completed"].includes(v.trim().toLowerCase());
  return v === true || v === 1;
};

// status badge: label + colour tone + icon
const STATUS_TONES = {
  active: "ok", approved: "ok", verified: "ok", complete: "ok", completed: "ok",
  pending: "warn", "in review": "warn",
  inactive: "bad", rejected: "bad", blocked: "bad", suspended: "bad", deleted: "bad",
};
const TONE_ICONS = { ok: <FaCheck />, warn: <FaClock />, bad: <FaTimes />, muted: <FaMinus /> };
const statusInfo = (raw) => {
  const s = fmt(raw);
  if (!s) return { label: NA, tone: "muted" };
  const label = s.charAt(0).toUpperCase() + s.slice(1).toLowerCase();
  return { label, tone: STATUS_TONES[s.toLowerCase()] || "muted" };
};

/* =====================================================================
   2. FIELD CONFIG  — change only the  k:  value to match your backend field names
      l = label, k = backend key (dotted path allowed), t = type,
      v = custom function (profile) => text,  wide = full row
      section.col: 1 = left card, 2 = right card, omitted = full width
      section.cols: 2 = rows laid out in two columns (for full-width cards)
   ===================================================================== */
const referenceSub = (p) => {
  const text = [fmt(pick(p, "reference_name")), fmt(pick(p, "reference_relation"))].filter(Boolean).join(" - ");
  return text ? `(${text})` : "";
};

const dobText = (p) => {
  const d = parseDate(p.dob);
  if (!d) return fmt(p.dob);
  const age = ageFrom(d);
  return `${longDate(d)}${age !== null ? ` (Age: ${age} Years)` : ""}`;
};

const weightText = (p) => {
  const w = fmt(p.weight);
  if (!w) return null;
  return /^\d+(\.\d+)?$/.test(w) ? `${w} kg` : w;
};

const TABS = [
  {
    id: "personal", label: "Personal Details", icon: <FaUser />,
    sections: [
      { title: "Basic Information", icon: <FaUser />, col: 1, fields: [
        { l: "Registration No.", k: "registration_no" }, { l: "Full Name", k: "full_name" },
        { l: "Date of Birth", v: dobText }, { l: "Gender", k: "gender" },
        { l: "Marital Status", k: "marital_status" }, { l: "Mobile Number", k: "mobile" },
        { l: "Email Address", k: "email" }, { l: "Profession", k: "profession" },
      ] },
      { title: "Community & Language", icon: <FaInfoCircle />, col: 1, fields: [
        { l: "Caste", k: "caste" }, { l: "Sub Caste", k: "sub_caste" },
        { l: "Mother Tongue", k: "mother_tongue" }, { l: "Languages Known", k: "languages_known" },
      ] },
      { title: "Professional Information", icon: <FaBriefcase />, col: 2, fields: [
        { l: "Income Range", k: "annual_income", t: "money" }, { l: "Occupation", k: "profession" },
        { l: "Company Name", k: "organisation" }, { l: "Designation", k: "designation" },
        { l: "Work Experience", k: "length_of_employment" },
      ] },
      { kind: "note", col: 2 },
      { title: "Health & Lifestyle", icon: <FaHeartbeat />, col: 2, fields: [
        { l: "Height", k: "height" }, { l: "Weight", v: weightText },
        { l: "Blood Group", k: "blood_group" }, { l: "Spectacles/Lenses", k: "spectacles" },
      ] },
      { title: "Horoscope Details", icon: <FaStar />, col: 2, fields: [
        { l: "Date of Birth", k: "horoscope_dob", t: "date" }, { l: "Time of Birth", k: "birth_time" },
        { l: "Place of Birth", k: "birth_place" }, { l: "Horoscope Caste", k: "horoscope_caste" },
        { l: "Horoscope Sub Caste", k: "horoscope_sub_caste" },
      ] },
    ],
  },
  {
    id: "family", label: "Family Details", icon: <FaUsers />,
    sections: [
      { title: "Family Details", icon: <FaUsers />, cols: 2, fields: [
        { l: "Father's Name", k: "father_name", suffix: "father_living" }, { l: "Father's Occupation", k: "father_occupation" },
        { l: "Father's Native Place", k: "father_native_place" }, { l: "Father's Contact", k: "father_phone" },
        { l: "Mother's Name", k: "mother_name", suffix: "mother_living" }, { l: "Mother's Occupation", k: "mother_occupation" },
        { l: "Mother's Native Place", k: "mother_native_place" }, { l: "Mother's Contact", k: "mother_phone" },
        { l: "Brothers", k: "brothers" }, { l: "Brothers Married", k: "brothers_married" },
        { l: "Sisters", k: "sisters" }, { l: "Sisters Married", k: "sisters_married" },
        { l: "Parents Intercaste Marriage", k: "parents_intercaste" }, { l: "Parents Different Caste", k: "parents_different_caste" },
        { l: "Family Financial Status", k: "family_financial_status" }, { l: "Family Annual Income", k: "family_income", t: "money" },
        { l: "Family Values", k: "family_values" },
      ] },
    ],
  },
  {
    id: "education", label: "Education & Profession", icon: <FaGraduationCap />,
    sections: [
      { title: "Education", icon: <FaGraduationCap />, col: 1, fields: [
        { l: "Highest Education", k: "education" }, { l: "University / College", k: "university" },
        { l: "Additional Education 1", k: "additional_education_1" }, { l: "Additional Education 2", k: "additional_education_2" },
      ] },
      { title: "Profession", icon: <FaBriefcase />, col: 2, fields: [
        { l: "Profession", k: "profession" }, { l: "Designation", k: "designation" }, { l: "Organisation", k: "organisation" },
        { l: "Field of Working", k: "field_of_working" }, { l: "Length of Employment", k: "length_of_employment" },
        { l: "Monthly Income", k: "monthly_income", t: "money" }, { l: "Annual Income", k: "annual_income", t: "money" },
      ] },
    ],
  },
  {
    id: "address", label: "Address Details", icon: <FaHome />,
    sections: [
      { title: "Address", icon: <FaMapMarkerAlt />, col: 1, fields: [
        { l: "Address", k: "address", wide: true }, { l: "Permanent Address", k: "permanent_address", wide: true },
        { l: "Country", k: "country" }, { l: "State", k: "state" }, { l: "District", k: "district" },
        { l: "City", k: "city" }, { l: "Taluka", k: "taluka" }, { l: "Village", k: "village" },
        { l: "Pincode", k: "pincode" },
      ] },
      { title: "Contact Details", icon: <FaAddressBook />, col: 2, fields: [
        { l: "Contact No", k: "mobile" }, { l: "Email Address", k: "email" },
        { l: "Father's Contact", k: "father_phone" }, { l: "Mother's Contact", k: "mother_phone" },
        { l: "Reference Contact", k: "reference_phone", sub: referenceSub },
      ] },
    ],
  },
  { id: "documents", label: "Documents", icon: <FaFileAlt /> },
  {
    id: "other", label: "Other Information", icon: <FaInfoCircle />,
    sections: [
      { title: "Partner Preferences", icon: <FaListUl />, cols: 2, fields: [
        { l: "Age Range", k: "pref_age_range" }, { l: "Height Range", k: "pref_height_range" },
        { l: "Preferred Religion", k: "pref_religion" }, { l: "Preferred Caste", k: "pref_caste" },
        { l: "Preferred Sub-Caste", k: "pref_sub_caste" }, { l: "Preferred Education", k: "pref_education" },
        { l: "Working Partner", k: "pref_working_partner" }, { l: "Preferred Working Field", k: "pref_working_field" },
        { l: "Expected Income", k: "pref_income" }, { l: "Family Type", k: "pref_family_type" },
        { l: "Financial Background", k: "pref_financial_background" }, { l: "Family Values", k: "pref_family_values" },
        { l: "Preferred Body Type", k: "pref_body_type" }, { l: "Preferred Location", k: "pref_location" },
        { l: "Preferred Country", k: "pref_country" }, { l: "Preferred State", k: "pref_state" }, { l: "Preferred City", k: "pref_city" },
      ] },
      { title: "Other Expectations", icon: <FaCommentDots />, kind: "text", k: "other_expectations", empty: "No other specific expectations mentioned." },
    ],
  },
];

const DOCUMENT_TYPES = [
  { key: "identificationProof", label: "Identification Proof" },
  { key: "birthDateProof", label: "Birth Date Proof" },
  { key: "addressProof", label: "Address Proof" },
  { key: "educationProof", label: "Education Proof" },
  { key: "professionProof", label: "Profession Proof" },
  { key: "incomeProof", label: "Income Proof" },
  { key: "casteCertificate", label: "Caste Certificate" },
  { key: "medicalFitnessCertificate", label: "Medical Fitness Certificate" },
  { key: "selfieVerification", label: "Selfie Verification" },
];

const YES_NO = [
  { value: "Yes", label: "Yes" },
  { value: "No", label: "No" },
];

const EDIT_TABS = {
  personal: [
    { title: "Basic Information", icon: <FaUser />, fields: [
      { k: "full_name", l: "Full Name" }, { k: "birth_date", l: "Date of Birth", type: "date" },
      { k: "gender", l: "Gender", type: "select", options: [{ value: "groom", label: "Groom" }, { value: "bride", label: "Bride" }] },
      { k: "marital_status", l: "Marital Status", type: "select", options: [
        { value: "Unmarried", label: "Unmarried" }, { value: "Married", label: "Married" },
        { value: "Divorced", label: "Divorced" }, { value: "Widowed", label: "Widowed" },
      ] },
      { k: "mobile", l: "Mobile Number", type: "tel" }, { k: "email", l: "Email Address", type: "email" },
      { k: "religion", l: "Religion" }, { k: "caste", l: "Caste" }, { k: "sub_caste", l: "Sub Caste" },
      { k: "mother_tongue", l: "Mother Tongue" }, { k: "languages_known", l: "Languages Known" },
      { k: "about", l: "About Candidate", type: "textarea", wide: true },
      { k: "special_cases", l: "Special Cases", type: "textarea", wide: true },
    ] },
    { title: "Health & Lifestyle", icon: <FaHeartbeat />, fields: [
      { k: "height", l: "Height" }, { k: "weight", l: "Weight" }, { k: "blood_group", l: "Blood Group" },
      { k: "spectacles", l: "Spectacles / Lenses", type: "select", options: YES_NO },
      { k: "has_medical_history", l: "Medical History", type: "select", options: YES_NO },
      { k: "personal_medical_history", l: "Personal Medical History", type: "textarea", wide: true },
      { k: "current_medical_problems", l: "Current Medical Problems", type: "textarea", wide: true },
      { k: "medications", l: "Medications", type: "textarea", wide: true },
    ] },
    { title: "Horoscope Details", icon: <FaStar />, fields: [
      { k: "horoscope_dob", l: "Horoscope Date of Birth", type: "date" },
      { k: "birth_time", l: "Time of Birth", type: "time" },
      { k: "birth_time_ampm", l: "AM / PM", type: "select", options: [{ value: "AM", label: "AM" }, { value: "PM", label: "PM" }] },
      { k: "birth_place", l: "Place of Birth" }, { k: "horoscope_caste", l: "Horoscope Caste" },
      { k: "horoscope_sub_caste", l: "Horoscope Sub Caste" },
    ] },
    { title: "Professional Information", icon: <FaBriefcase />, fields: [
      { k: "profession", l: "Profession" }, { k: "organisation", l: "Organisation" },
      { k: "designation", l: "Designation" }, { k: "field_of_working", l: "Field of Working" },
      { k: "length_of_employment", l: "Length of Employment" }, { k: "monthly_income", l: "Monthly Income" },
      { k: "annual_income", l: "Annual Income" },
      { k: "income_currency", l: "Income Currency", type: "select", options: [
        { value: "INR", label: "INR" }, { value: "USD", label: "USD" }, { value: "EUR", label: "EUR" }, { value: "GBP", label: "GBP" },
      ] },
    ] },
  ],
  family: [
    { title: "Family Details", icon: <FaUsers />, fields: [
      { k: "father_name", l: "Father's Name" }, { k: "father_living", l: "Father Living", type: "select", options: YES_NO },
      { k: "father_occupation", l: "Father's Occupation" }, { k: "father_native_place", l: "Father's Native Place" },
      { k: "father_phone", l: "Father's Contact", type: "tel" }, { k: "mother_name", l: "Mother's Name" },
      { k: "mother_living", l: "Mother Living", type: "select", options: YES_NO },
      { k: "mother_occupation", l: "Mother's Occupation" }, { k: "mother_native_place", l: "Mother's Native Place" },
      { k: "mother_phone", l: "Mother's Contact", type: "tel" }, { k: "brothers", l: "Brothers", type: "number" },
      { k: "brothers_married", l: "Brothers Married", type: "number" }, { k: "sisters", l: "Sisters", type: "number" },
      { k: "sisters_married", l: "Sisters Married", type: "number" },
      { k: "parents_intercaste", l: "Parents Intercaste Marriage", type: "select", options: YES_NO },
      { k: "parents_different_caste", l: "Parents Different Caste" },
      { k: "family_financial_status", l: "Family Financial Status" }, { k: "family_income", l: "Family Annual Income" },
      { k: "family_values", l: "Family Values" },
    ] },
    { title: "Reference & Creator", icon: <FaAddressBook />, fields: [
      { k: "reference_name", l: "Reference Name" }, { k: "reference_relation", l: "Reference Relation" },
      { k: "reference_address", l: "Reference Address", type: "textarea", wide: true },
      { k: "reference_phone", l: "Reference Contact", type: "tel" }, { k: "reference_email", l: "Reference Email", type: "email" },
      { k: "profile_creator_name", l: "Profile Creator Name" }, { k: "profile_creator_relation", l: "Profile Creator Relation" },
    ] },
  ],
  education: [
    { title: "Education", icon: <FaGraduationCap />, fields: [
      { k: "education", l: "Highest Education" }, { k: "university", l: "University / College" },
      { k: "degree_diploma_name_1", l: "Degree / Diploma Name 1" }, { k: "degree_diploma_name_2", l: "Degree / Diploma Name 2" },
      { k: "specialisation_1", l: "Specialisation 1" }, { k: "specialisation_2", l: "Specialisation 2" },
      { k: "additional_education_1", l: "Additional Education 1" }, { k: "additional_education_2", l: "Additional Education 2" },
      { k: "city_of_education", l: "City of Education" },
    ] },
    { title: "Profession", icon: <FaBriefcase />, fields: [
      { k: "profession", l: "Profession" }, { k: "designation", l: "Designation" }, { k: "organisation", l: "Organisation" },
      { k: "field_of_working", l: "Field of Working" }, { k: "length_of_employment", l: "Length of Employment" },
      { k: "monthly_income", l: "Monthly Income" }, { k: "annual_income", l: "Annual Income" },
      { k: "income_currency", l: "Income Currency", type: "select", options: [
        { value: "INR", label: "INR" }, { value: "USD", label: "USD" }, { value: "EUR", label: "EUR" }, { value: "GBP", label: "GBP" },
      ] },
    ] },
  ],
  address: [
    { title: "Address", icon: <FaMapMarkerAlt />, fields: [
      { k: "address", l: "Address", type: "textarea", wide: true },
      { k: "permanent_address", l: "Permanent Address", type: "textarea", wide: true },
      { k: "country", l: "Country" }, { k: "state", l: "State" }, { k: "district", l: "District" },
      { k: "city", l: "City" }, { k: "taluka", l: "Taluka" }, { k: "village", l: "Village" },
      { k: "pincode", l: "Pincode" },
    ] },
    { title: "Contact Details", icon: <FaAddressBook />, fields: [
      { k: "mobile", l: "Contact No", type: "tel" }, { k: "email", l: "Email Address", type: "email" },
      { k: "father_phone", l: "Father's Contact", type: "tel" }, { k: "mother_phone", l: "Mother's Contact", type: "tel" },
      { k: "reference_phone", l: "Reference Contact", type: "tel" },
    ] },
  ],
  other: [
    { title: "Partner Preferences", icon: <FaListUl />, fields: [
      { k: "pref_age_from", l: "Preferred Age From", type: "number" }, { k: "pref_age_to", l: "Preferred Age To", type: "number" },
      { k: "pref_height_from", l: "Preferred Height From" }, { k: "pref_height_to", l: "Preferred Height To" },
      { k: "pref_religion", l: "Preferred Religion" }, { k: "pref_caste", l: "Preferred Caste" },
      { k: "pref_sub_caste", l: "Preferred Sub-Caste" }, { k: "pref_education", l: "Preferred Education" },
      { k: "pref_working_partner", l: "Working Partner" }, { k: "pref_working_field", l: "Preferred Working Field" },
      { k: "pref_income", l: "Expected Income" }, { k: "pref_family_type", l: "Family Type" },
      { k: "pref_financial_background", l: "Financial Background" }, { k: "pref_family_values", l: "Family Values" },
      { k: "pref_body_type", l: "Preferred Body Type" }, { k: "pref_location", l: "Preferred Location" },
      { k: "pref_country", l: "Preferred Country" }, { k: "pref_state", l: "Preferred State" },
      { k: "pref_city", l: "Preferred City" },
      { k: "other_expectations", l: "Other Expectations", type: "textarea", wide: true },
    ] },
  ],
};

const EDIT_FIELD_MAP = Object.fromEntries(
  Object.values(EDIT_TABS).flatMap((groups) => groups.flatMap((group) => group.fields)).map((field) => [field.k, field]),
);
const EDIT_FIELD_KEYS = Object.keys(EDIT_FIELD_MAP);
const DATE_FIELDS = new Set(EDIT_FIELD_KEYS.filter((key) => EDIT_FIELD_MAP[key].type === "date"));
const TIME_FIELDS = new Set(EDIT_FIELD_KEYS.filter((key) => EDIT_FIELD_MAP[key].type === "time"));

const toDateInput = (value) => {
  if (!value) return "";
  const text = String(value).trim();
  const iso = /^(\d{4})-(\d{2})-(\d{2})/.exec(text);
  if (iso) return `${iso[1]}-${iso[2]}-${iso[3]}`;
  const dmy = /^(\d{1,2})[-/](\d{1,2})[-/](\d{4})$/.exec(text);
  if (dmy) return `${dmy[3]}-${dmy[2].padStart(2, "0")}-${dmy[1].padStart(2, "0")}`;
  const parsed = parseDate(text);
  if (!parsed) return "";
  return `${parsed.getFullYear()}-${String(parsed.getMonth() + 1).padStart(2, "0")}-${String(parsed.getDate()).padStart(2, "0")}`;
};

const toTimeInput = (value) => {
  if (!value) return "";
  const text = String(value).trim();
  const hm = /^(\d{1,2}):(\d{2})/.exec(text);
  if (hm) return `${hm[1].padStart(2, "0")}:${hm[2]}`;
  const ampm = /^(\d{1,2}):(\d{2})\s*(AM|PM)$/i.exec(text);
  if (!ampm) return "";
  let hours = Number(ampm[1]) % 12;
  if (ampm[3].toUpperCase() === "PM") hours += 12;
  return `${String(hours).padStart(2, "0")}:${ampm[2]}`;
};

const cleanNumber = (value) => String(value ?? "").replace(/[^\d.-]/g, "");

const buildEditForm = (profile) => {
  const form = {};
  EDIT_FIELD_KEYS.forEach((key) => {
    const raw = profile?.[key];
    if (DATE_FIELDS.has(key)) form[key] = toDateInput(raw);
    else if (TIME_FIELDS.has(key)) form[key] = toTimeInput(raw);
    else if (key === "monthly_income" || key === "annual_income") form[key] = cleanNumber(raw);
    else form[key] = raw === null || raw === undefined ? "" : String(raw);
  });
  if (!form.income_currency) form.income_currency = "INR";
  return form;
};

/* =====================================================================
   3. SMALL COMPONENTS
   ===================================================================== */
function Field({ f, profile }) {
  const value = f.v ? f.v(profile) : fmt(pick(profile, f.k), f.t);
  const suffix = f.suffix ? fmt(pick(profile, f.suffix)) : null;
  const sub = value && f.sub ? f.sub(profile) : "";
  return (
    <div className={`pd-row${f.wide ? " pd-row-wide" : ""}`}>
      <span className="pd-label">{f.l}</span>
      <span className="pd-colon" aria-hidden="true">:</span>
      <span className={`pd-value${value ? "" : " pd-empty"}`}>
        {value ? <>{value}{suffix && <em> ({suffix})</em>}</> : NA}
      </span>
      {sub && <small className="pd-sub">{sub}</small>}
    </div>
  );
}

function Note({ profile }) {
  const verified = isVerified(profile);
  return (
    <div className="pd-note">
      <span className="pd-note-ic"><FaStar /></span>
      <p>
        <strong>Note:</strong>{" "}
        {verified
          ? "This candidate is verified and eligible for Melava registration."
          : "This candidate is not verified yet. Verify the profile to make it eligible for Melava registration."}
      </p>
    </div>
  );
}

function Card({ s, profile }) {
  if (s.kind === "note") return <Note profile={profile} />;
  return (
    <section className="pd-card">
      <h3 className="pd-card-head"><span className="pd-card-ic">{s.icon}</span>{s.title}</h3>
      <div className="pd-card-body">
        {s.kind === "text" ? (
          <p className="pd-text">{fmt(pick(profile, s.k)) || s.empty}</p>
        ) : (
          <div className={`pd-rows${s.cols === 2 ? " pd-rows-2" : ""}`}>
            {s.fields.map((f, i) => <Field key={`${f.l}-${i}`} f={f} profile={profile} />)}
          </div>
        )}
      </div>
    </section>
  );
}

// lays out the cards of a tab: left column, right column, then full-width cards
function Cards({ sections, profile }) {
  const left = sections.filter((s) => s.col === 1);
  const right = sections.filter((s) => s.col === 2);
  const full = sections.filter((s) => !s.col);
  const keyOf = (s) => s.title || s.kind;
  return (
    <>
      {(left.length > 0 || right.length > 0) && (
        <div className="pd-cols">
          <div className="pd-col">{left.map((s) => <Card key={keyOf(s)} s={s} profile={profile} />)}</div>
          <div className="pd-col">{right.map((s) => <Card key={keyOf(s)} s={s} profile={profile} />)}</div>
        </div>
      )}
      {full.map((s) => <Card key={keyOf(s)} s={s} profile={profile} />)}
    </>
  );
}

function EditField({ field, value, onChange }) {
  const id = `pd-edit-${field.k}`;
  const common = {
    id,
    name: field.k,
    value: value ?? "",
    onChange: (event) => onChange(field.k, event.target.value),
    className: "pd-input",
  };

  let control;
  if (field.type === "select") {
    const options = [...field.options];
    if (value && !options.some((option) => option.value === value)) {
      options.push({ value, label: value });
    }
    control = (
      <select {...common}>
        <option value="">Select</option>
        {options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
      </select>
    );
  } else if (field.type === "textarea") {
    control = <textarea {...common} rows={3} />;
  } else {
    control = <input {...common} type={field.type || "text"} />;
  }

  return (
    <label className={`pd-field${field.wide ? " pd-field-wide" : ""}`} htmlFor={id}>
      <span>{field.l}</span>
      {control}
    </label>
  );
}

function EditCard({ group, form, onChange }) {
  return (
    <section className="pd-card">
      <h3 className="pd-card-head"><span className="pd-card-ic">{group.icon}</span>{group.title}</h3>
      <div className="pd-card-body">
        <div className="pd-form-grid">
          {group.fields.map((field) => (
            <EditField key={field.k} field={field} value={form[field.k]} onChange={onChange} />
          ))}
        </div>
      </div>
    </section>
  );
}

function EditGroups({ groups, form, onChange }) {
  return groups.map((group) => <EditCard key={group.title} group={group} form={form} onChange={onChange} />);
}

function DocumentEditor({ docs, files, onFileChange, onOpen, openingId }) {
  const existing = new Map(docs.map((doc) => [String(doc.id), doc]));

  return (
    <section className="pd-card">
      <h3 className="pd-card-head"><span className="pd-card-ic"><FaFileAlt /></span>Documents</h3>
      <div className="pd-card-body">
        <p className="pd-edit-hint">Upload a replacement only when needed. Existing documents are kept if no new file is selected.</p>
        <div className="pd-doc-editor">
          {DOCUMENT_TYPES.map((type) => {
            const doc = existing.get(type.key);
            const file = files[type.key];
            return (
              <div className="pd-doc-edit" key={type.key}>
                <div className="pd-doc-edit-info">
                  <strong>{type.label}</strong>
                  <span className={doc ? "pd-doc-present" : "pd-doc-missing"}>
                    {doc ? "Uploaded" : "Not uploaded"}
                  </span>
                  {file && <span className="pd-doc-file-name">{file.name}</span>}
                </div>
                <div className="pd-doc-edit-actions">
                  {doc && (
                    <button type="button" className="pd-btn pd-btn-sm pd-btn-outline" onClick={() => onOpen(doc)} disabled={openingId === doc.id}>
                      <FaEye /> {openingId === doc.id ? "Opening…" : "View"}
                    </button>
                  )}
                  <label className="pd-btn pd-btn-sm pd-btn-teal pd-file-label">
                    <FaUpload /> {doc ? "Replace" : "Upload"}
                    <input
                      type="file"
                      accept=".pdf,.jpg,.jpeg,.png"
                      onChange={(event) => {
                        onFileChange(type.key, event.target.files?.[0] || null);
                        event.target.value = "";
                      }}
                    />
                  </label>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}

function Documents({ docs, onOpen, openingId, onDelete, deletingId }) {
  return (
    <section className="pd-card">
      <h3 className="pd-card-head"><span className="pd-card-ic"><FaFileAlt /></span>Uploaded Documents</h3>
      <div className="pd-card-body">
        {docs.length === 0 ? (
          <p className="pd-text">No documents have been uploaded yet.</p>
        ) : (
          <ul className="pd-docs">
            {docs.map((d) => (
              <li key={d.id}>
                <span className="pd-doc-ic">{/birth/i.test(d.label) ? <FaCalendarAlt /> : <FaIdCard />}</span>
                <button type="button" className="pd-doc-link" onClick={() => onOpen(d)} disabled={openingId === d.id}>
                  {openingId === d.id ? "Opening…" : d.label}
                </button>
                <button type="button" className="pd-icon-btn" aria-label={`Delete ${d.label}`}
                  disabled={deletingId === d.id} onClick={() => onDelete(d)}>
                  <FaTrash />
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </section>
  );
}

function Gallery({ photos }) {
  const [viewerIndex, setViewerIndex] = useState(null);
  const [playing, setPlaying] = useState(false);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [broken, setBroken] = useState({});
  const viewerRef = useRef(null);
  const touchStartRef = useRef(null);

  const isOpen = viewerIndex !== null;
  const safeIndex = viewerIndex === null ? null : Math.min(Math.max(viewerIndex, 0), Math.max(photos.length - 1, 0));
  const activePhoto = safeIndex === null ? null : photos[safeIndex] || null;

  const step = useCallback((direction) => {
    setViewerIndex((current) => {
      if (current === null || photos.length === 0) return current;
      return (current + direction + photos.length) % photos.length;
    });
  }, [photos.length]);

  const closeViewer = useCallback(() => {
    setViewerIndex(null);
    setPlaying(false);
    if (typeof document !== "undefined" && document.fullscreenElement) {
      const result = document.exitFullscreen?.();
      if (result && typeof result.catch === "function") result.catch(() => {});
    }
  }, []);

  useEffect(() => {
    if (!isOpen) return undefined;
    const previous = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.body.style.overflow = previous;
    };
  }, [isOpen]);

  useEffect(() => {
    if (!isOpen) return undefined;
    const onKeyDown = (event) => {
      if (event.key === "ArrowLeft") {
        event.preventDefault();
        step(-1);
      } else if (event.key === "ArrowRight") {
        event.preventDefault();
        step(1);
      } else if (event.key === "Escape") {
        closeViewer();
      } else if (event.key === " ") {
        event.preventDefault();
        setPlaying((value) => !value);
      }
    };
    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [isOpen, step, closeViewer]);

  useEffect(() => {
    if (!isOpen || !playing || photos.length < 2) return undefined;
    const timer = window.setInterval(() => step(1), 3000);
    return () => window.clearInterval(timer);
  }, [isOpen, playing, photos.length, step]);

  useEffect(() => {
    const onFullscreenChange = () => setIsFullscreen(Boolean(document.fullscreenElement));
    document.addEventListener("fullscreenchange", onFullscreenChange);
    return () => document.removeEventListener("fullscreenchange", onFullscreenChange);
  }, []);

  const toggleFullscreen = async () => {
    try {
      if (!document.fullscreenElement) {
        await viewerRef.current?.requestFullscreen?.();
      } else {
        await document.exitFullscreen?.();
      }
    } catch {
      /* fullscreen is not available in this browser */
    }
  };

  const markBroken = (id) => setBroken((current) => ({ ...current, [id]: true }));

  const handleTouchStart = (event) => {
    touchStartRef.current = event.touches?.[0]?.clientX ?? null;
  };

  const handleTouchEnd = (event) => {
    if (touchStartRef.current === null) return;
    const endX = event.changedTouches?.[0]?.clientX ?? touchStartRef.current;
    const delta = endX - touchStartRef.current;
    touchStartRef.current = null;
    if (Math.abs(delta) > 50) step(delta > 0 ? -1 : 1);
  };

  return (
    <section className="pd-gallery-card">
      <div className="pd-gallery-head">
        <h3><span className="pd-card-ic"><FaImages /></span>Photo Gallery</h3>
        <span className="pd-gallery-count">{photos.length} {photos.length === 1 ? "photo" : "photos"}</span>
      </div>

      {photos.length === 0 ? (
        <div className="pd-gallery-empty">
          <FaImages />
          <p>No candidate photos are available yet.</p>
        </div>
      ) : (
        <div className="pd-gallery-grid">
          {photos.map((photo, index) => (
            <button
              key={photo.id}
              type="button"
              className="pd-gallery-thumb"
              onClick={() => { setViewerIndex(index); setPlaying(false); }}
              aria-label={`Open ${photo.label}`}
            >
              {broken[photo.id] ? (
                <span className="pd-gallery-broken"><FaImages /><small>{photo.label}</small></span>
              ) : (
                <img src={photo.url} alt={photo.label} loading="lazy" onError={() => markBroken(photo.id)} />
              )}
              <span className="pd-gallery-label">{photo.label}</span>
            </button>
          ))}
        </div>
      )}

      {isOpen && activePhoto && (
        <div
          className="pd-viewer"
          ref={viewerRef}
          role="dialog"
          aria-modal="true"
          aria-label="Photo viewer"
          onTouchStart={handleTouchStart}
          onTouchEnd={handleTouchEnd}
        >
          <div className="pd-viewer-top">
            <span className="pd-viewer-counter">{(safeIndex ?? 0) + 1} / {photos.length}</span>
            <div className="pd-viewer-actions">
              <button
                type="button"
                title={playing ? "Pause slideshow" : "Start slideshow"}
                onClick={() => setPlaying((value) => !value)}
                disabled={photos.length < 2}
              >
                {playing ? <FaPause /> : <FaPlay />}
              </button>
              <button type="button" title="Toggle fullscreen" onClick={toggleFullscreen}>
                {isFullscreen ? <FaCompress /> : <FaExpand />}
              </button>
              <button type="button" title="Close viewer" onClick={closeViewer}><FaTimes /></button>
            </div>
          </div>

          <button type="button" className="pd-viewer-nav pd-viewer-prev" onClick={() => step(-1)} aria-label="Previous photo">
            <FaChevronLeft />
          </button>

          <div className="pd-viewer-stage">
            {broken[activePhoto.id] ? (
              <div className="pd-viewer-broken"><FaImages /><p>Image unavailable</p></div>
            ) : (
              <img
                key={activePhoto.id}
                className="pd-viewer-img"
                src={activePhoto.url}
                alt={activePhoto.label}
                onError={() => markBroken(activePhoto.id)}
              />
            )}
            <p className="pd-viewer-caption">{activePhoto.label}</p>
          </div>

          <button type="button" className="pd-viewer-nav pd-viewer-next" onClick={() => step(1)} aria-label="Next photo">
            <FaChevronRight />
          </button>

          {photos.length > 1 && (
            <div className="pd-viewer-dots">
              {photos.map((photo, index) => (
                <button
                  key={photo.id}
                  type="button"
                  className={index === safeIndex ? "active" : ""}
                  aria-label={`Go to ${photo.label}`}
                  onClick={() => setViewerIndex(index)}
                />
              ))}
            </div>
          )}
        </div>
      )}
    </section>
  );
}

function Hero({ p, onView, onEdit, onDownload, downloading, editing }) {
  const [imgFailed, setImgFailed] = useState(false);
  const name = fmt(p.full_name) || "Unnamed profile";
  const initials = name.split(/\s+/).slice(0, 2).map((w) => w[0]).join("").toUpperCase();
  const verified = isVerified(p);
  const type = fmt(p.registration_label) || fmt(p.profile_type ?? p.category) || fmt(p.registration_type);
  const reg = fmt(p.registration_no);
  const place = [fmt(p.city), fmt(p.state), fmt(p.country)].filter(Boolean).join(", ") || fmt(p.address);
  const status = statusInfo(p.status);
  const registered = parseDate(p.created_at ?? p.registered_at ?? p.registration_date);
  const docTotal = Number(p.documents_total ?? 0);
  const docVerified = Number(p.documents_verified ?? 0);
  const verificationNote = docTotal > 0
    ? `${docVerified}/${docTotal} documents verified · Payment ${p.payment_verified ? "verified" : "pending"}`
    : "";

  const contacts = [
    [<FaPhoneAlt />, fmt(p.mobile)],
    [<FaEnvelope />, fmt(p.email)],
    [<FaMapMarkerAlt />, place],
  ].filter(([, text]) => text);

  return (
    <section className="pd-hero">
      <div className="pd-avatar-wrap">
        <div className="pd-avatar">
          {p._photo && !imgFailed
            ? <img src={p._photo} alt={name} onError={() => setImgFailed(true)} />
            : <span aria-hidden="true">{initials}</span>}
        </div>
        {verified && <span className="pd-verified-pill"><FaCheck /> Verified</span>}
      </div>

      <div className="pd-ident">
        <h2 className="pd-name">
          {name}
          {verified && <FaCheckCircle className="pd-name-badge" aria-label="Verified profile" />}
        </h2>
        {(type || reg) && (
          <div className="pd-pill">
            {type && <strong>{type}</strong>}
            {type && reg && <span className="pd-dot">•</span>}
            {reg && <span>ID: {reg}</span>}
          </div>
        )}
        <ul className="pd-contacts">
          {contacts.map(([icon, text], i) => <li key={i}><span className="pd-contact-ic">{icon}</span><span>{text}</span></li>)}
        </ul>
      </div>

      <div className={`pd-status pd-tone-${status.tone}`}>
        <h3 className="pd-block-title">Current Status</h3>
        <div className="pd-status-body">
          <span className="pd-status-ring"><span className="pd-status-core">{TONE_ICONS[status.tone]}</span></span>
          <div>
            <div className="pd-status-label">{status.label}</div>
            {verificationNote && <div className="pd-status-sub">{verificationNote}</div>}
            {registered && <div className="pd-status-sub">Registered on<br />{longDate(registered)}</div>}
          </div>
        </div>
      </div>

      <div className="pd-quick">
        <h3 className="pd-block-title">Quick Actions</h3>
        <button type="button" className="pd-qa pd-qa-blue" onClick={onView}><FaEye /> View Details</button>
        {!editing && <button type="button" className="pd-qa pd-qa-teal" onClick={onEdit}><FaEdit /> Edit Profile</button>}
        <button type="button" className="pd-qa pd-qa-green" onClick={onDownload} disabled={downloading}>
          <FaDownload /> {downloading ? "Preparing…" : "BioData Download"}
        </button>
      </div>
    </section>
  );
}

function Skeleton() {
  return (
    <div aria-busy="true" aria-label="Loading profile">
      <div className="pd-hero pd-hero-sk">
        <div className="pd-avatar pd-shimmer" />
        <div>
          <div className="pd-sk pd-sk-name pd-shimmer" />
          {[1, 2, 3].map((i) => <div key={i} className="pd-sk pd-shimmer" />)}
        </div>
        <div>{[1, 2].map((i) => <div key={i} className="pd-sk pd-shimmer" />)}</div>
        <div>{[1, 2, 3].map((i) => <div key={i} className="pd-sk pd-shimmer" />)}</div>
      </div>
      <div className="pd-sk pd-sk-tabs pd-shimmer" />
      <div className="pd-panel pd-panel-first">
        {[1, 2, 3, 4, 5, 6].map((i) => <div key={i} className="pd-sk pd-shimmer" />)}
      </div>
    </div>
  );
}

function StateCard({ title, message, onRetry }) {
  return (
    <div className="pd-state" role="alert">
      <h2>{title}</h2>
      <p>{message}</p>
      <div>
        {onRetry && <button type="button" className="pd-btn pd-btn-teal" onClick={onRetry}><FaRedo /> Try again</button>}
        <Link to="/admin/profiles" className="pd-btn pd-btn-outline">Back to List</Link>
      </div>
    </div>
  );
}

/* =====================================================================
   4. PAGE
   ===================================================================== */
export default function ProfileDetail({ params, startInEdit = false }) {
  const routeParams = useParams();
  const id = params?.id ?? routeParams.id;
  const idOk = isValidId(id);

  const [state, setState] = useState({ status: "loading", profile: null, error: "", notFound: false });
  const [tab, setTab] = useState("personal");
  const [notice, setNotice] = useState(null); // { type: "success" | "error", text }
  const [bioData, setBioData] = useState(null);
  const [loadingBioData, setLoadingBioData] = useState(false);
  const [openingId, setOpeningId] = useState(null);
  const [deletingId, setDeletingId] = useState(null);
  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState({});
  const [documentFiles, setDocumentFiles] = useState({});
  const [saving, setSaving] = useState(false);

  const load = useCallback(async (signal) => {
    setState({ status: "loading", profile: null, error: "", notFound: false });
    try {
      const profile = await getProfile(id, signal);
      setState({ status: "ready", profile, error: "", notFound: false });
    } catch (err) {
      if (err.name === "AbortError") return;
      setState({ status: "error", profile: null, error: err.message || "Something went wrong.", notFound: err.status === 404 });
    }
  }, [id]);

  useEffect(() => {
    if (!idOk) return undefined;
    const controller = new AbortController();
    const timer = window.setTimeout(() => load(controller.signal), 0);
    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  }, [idOk, load]);

  // success messages disappear by themselves
  useEffect(() => {
    if (notice?.type !== "success") return undefined;
    const t = setTimeout(() => setNotice(null), 4000);
    return () => clearTimeout(t);
  }, [notice]);

  const profile = state.profile;

  useEffect(() => {
    if (startInEdit && profile && !editing) {
      setForm(buildEditForm(profile));
      setDocumentFiles({});
      setEditing(true);
    }
  }, [startInEdit, profile, editing]);

  const goToTab = (tabId) => {
    setTab(tabId);
    window.setTimeout(() => {
      document.getElementById("pd-tabs")?.scrollIntoView({ behavior: "smooth", block: "start" });
    }, 0);
  };

  const handleOpenBioData = async () => {
    setLoadingBioData(true);
    setNotice(null);
    try {
      setBioData(await getBiodataPreview(id));
    } catch (err) {
      setNotice({ type: "error", text: err.message || "Could not load BioData templates." });
    } finally {
      setLoadingBioData(false);
    }
  };

  const startEditing = () => {
    if (!profile) return;
    setForm(buildEditForm(profile));
    setDocumentFiles({});
    setEditing(true);
    setNotice(null);
  };

  const cancelEditing = () => {
    setEditing(false);
    setForm({});
    setDocumentFiles({});
    setNotice(null);
  };

  const handleFieldChange = (key, value) => {
    setForm((current) => ({ ...current, [key]: value }));
  };

  const handleDocumentFile = (key, file) => {
    if (!file) {
      setDocumentFiles((current) => {
        const next = { ...current };
        delete next[key];
        return next;
      });
      return;
    }

    const allowed = ["application/pdf", "image/jpeg", "image/png"];
    const extensionOk = /\.(pdf|jpe?g|png)$/i.test(file.name);
    if ((!allowed.includes(file.type) && !extensionOk) || file.size > 50 * 1024 * 1024) {
      setNotice({ type: "error", text: "Documents must be PDF, JPG, JPEG, or PNG and no larger than 50 MB." });
      return;
    }

    setDocumentFiles((current) => ({ ...current, [key]: file }));
  };

  const handleSave = async () => {
    if (!form.full_name?.trim()) {
      setNotice({ type: "error", text: "Full name is required." });
      return;
    }
    if (!form.birth_date) {
      setNotice({ type: "error", text: "Date of birth is required." });
      return;
    }
    if (!form.gender) {
      setNotice({ type: "error", text: "Gender is required." });
      return;
    }

    setSaving(true);
    setNotice(null);
    try {
      const updated = await updateProfile(id, form, documentFiles);
      setState({ status: "ready", profile: updated, error: "", notFound: false });
      setEditing(false);
      setForm({});
      setDocumentFiles({});
      setNotice({ type: "success", text: "Profile updated successfully." });
    } catch (err) {
      setNotice({ type: "error", text: err.message || "Could not update this profile." });
    } finally {
      setSaving(false);
    }
  };

  const handleOpenDocument = async (doc) => {
    const documentTab = window.open("about:blank", "_blank");
    if (!documentTab) {
      setNotice({ type: "error", text: "Allow pop-ups to open the document in a new tab." });
      return;
    }

    documentTab.opener = null;
    setOpeningId(doc.id);
    setNotice(null);
    try {
      const blob = await getDocumentFile(id, doc.id);
      const documentUrl = URL.createObjectURL(blob);
      documentTab.location.replace(documentUrl);
      window.setTimeout(() => URL.revokeObjectURL(documentUrl), 60000);
    } catch (err) {
      documentTab.close();
      setNotice({ type: "error", text: err.message || "Could not open this document." });
    } finally {
      setOpeningId(null);
    }
  };

  const handleDeleteDoc = async (doc) => {
    if (!window.confirm(`Delete "${doc.label}"? This cannot be undone.`)) return;
    setDeletingId(doc.id);
    setNotice(null);
    try {
      await deleteDocument(id, doc.id);
      setState((s) => ({ ...s, profile: { ...s.profile, documents: s.profile.documents.filter((d) => d.id !== doc.id) } }));
      setNotice({ type: "success", text: `${doc.label} deleted.` });
    } catch (err) {
      setNotice({ type: "error", text: err.message || "Could not delete the document." });
    } finally {
      setDeletingId(null);
    }
  };

  const ready = idOk && state.status === "ready";

  let body;
  if (!idOk) {
    body = <StateCard title="Invalid profile link" message="The profile id in this link is not valid. Open the profile from the Profiles list." />;
  } else if (state.status === "loading") {
    body = <Skeleton />;
  } else if (state.status === "error") {
    body = <StateCard title={state.notFound ? "Profile not found" : "Could not load this profile"}
      message={state.error} onRetry={state.notFound ? undefined : () => load()} />;
  } else {
    const activeIndex = Math.max(0, TABS.findIndex((t) => t.id === tab));
    const active = TABS[activeIndex];
    body = (
      <>
        <Hero p={profile} onView={() => goToTab("personal")} onEdit={startEditing}
          onDownload={handleOpenBioData} downloading={loadingBioData} editing={editing} />

        <Gallery photos={profile.photos || []} />

        <div id="pd-tabs" className="pd-tabs" role="tablist" aria-label="Profile sections">
          {TABS.map((t) => (
            <button key={t.id} type="button" role="tab" id={`pd-tab-${t.id}`} aria-selected={active.id === t.id}
              aria-controls="pd-panel" className={`pd-tab${active.id === t.id ? " active" : ""}`} onClick={() => setTab(t.id)}>
              <span className="pd-tab-ic">{t.icon}</span>{t.label}
            </button>
          ))}
        </div>
        <div id="pd-panel" role="tabpanel" aria-labelledby={`pd-tab-${active.id}`}
          className={`pd-panel${activeIndex === 0 ? " pd-panel-first" : ""}`}>
          {editing ? (
            active.id === "documents" ? (
              <DocumentEditor docs={profile.documents} files={documentFiles} onFileChange={handleDocumentFile}
                onOpen={handleOpenDocument} openingId={openingId} />
            ) : (
              <EditGroups groups={EDIT_TABS[active.id] || []} form={form} onChange={handleFieldChange} />
            )
          ) : active.id === "documents" ? (
            <Documents docs={profile.documents} onOpen={handleOpenDocument} openingId={openingId}
              onDelete={handleDeleteDoc} deletingId={deletingId} />
          ) : (
            <Cards sections={active.sections} profile={profile} />
          )}
        </div>
        {editing && (
          <div className="pd-edit-actions">
            <button type="button" className="pd-btn pd-btn-green" onClick={handleSave} disabled={saving}>
              <FaCheck /> {saving ? "Updating…" : "Update Profile"}
            </button>
            <button type="button" className="pd-btn pd-btn-outline" onClick={cancelEditing} disabled={saving}>
              <FaTimes /> Cancel
            </button>
          </div>
        )}
      </>
    );
  }

  return (
    <AdminLayout currentPath="/admin/profiles" contentClassName="adm-content-profile-detail">
      <div className="pd">
        <header className="pd-head">
          <div className="pd-title">
            <span className="pd-title-ic"><FaUsers /></span>
            <div>
              <h1>Candidate Details</h1>
              <p>Complete information about the selected candidate</p>
            </div>
          </div>
          <div className="pd-head-actions">
            <Link to="/admin/profiles" className="pd-btn pd-btn-outline"><FaArrowLeft /> Back to List</Link>
            {ready && (
              <>
                <button type="button" className="pd-btn pd-btn-outline" onClick={() => goToTab("documents")}>
                  <FaFileAlt /> Documents
                </button>
                {!editing && (isVerified(profile)
                  ? <button type="button" className="pd-btn pd-btn-green" disabled><FaShieldAlt /> Verified</button>
                  : <Link to={`/admin/profiles/${id}`} className="pd-btn pd-btn-green"><FaShieldAlt /> Verify Profile</Link>)}
                {!editing && <button type="button" className="pd-btn pd-btn-teal" onClick={startEditing}><FaEdit /> Edit Profile</button>}
              </>
            )}
          </div>
        </header>

        {notice && (
          <div className={`pd-alert pd-alert-${notice.type}`} role="alert">
            <span>{notice.text}</span>
            <button type="button" aria-label="Dismiss message" onClick={() => setNotice(null)}>×</button>
          </div>
        )}
        {body}
        {bioData && <BioDataModal profile={bioData} onClose={() => setBioData(null)} />}
      </div>
    </AdminLayout>
  );
}