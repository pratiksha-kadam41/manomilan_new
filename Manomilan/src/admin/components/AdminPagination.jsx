import { ChevronLeft, ChevronRight } from "lucide-react";

function pageList(current, last) {
  const values = new Set([1, 2, last - 1, last, current - 1, current, current + 1]);
  const numbers = [...values].filter((value) => value >= 1 && value <= last).sort((a, b) => a - b);
  const output = [];
  numbers.forEach((number, index) => {
    if (index && number - numbers[index - 1] > 1) output.push(`gap-${number}`);
    output.push(number);
  });
  return output;
}

export default function AdminPagination({
  page = 1,
  lastPage = 1,
  from = 0,
  to = 0,
  total = 0,
  onPage,
  disabled = false,
  label = "Pagination",
}) {
  if (!lastPage || lastPage < 1) return null;

  return (
    <div className="ui-foot">
      <span>
        Showing {Number(from || 0).toLocaleString("en-IN")}–{Number(to || 0).toLocaleString("en-IN")} of{" "}
        {Number(total || 0).toLocaleString("en-IN")}
      </span>
      <nav className="ui-pages" aria-label={label}>
        <button
          type="button"
          disabled={disabled || page <= 1}
          onClick={() => onPage?.(page - 1)}
          aria-label="Previous page"
        >
          <ChevronLeft size={16} />
        </button>
        {pageList(page, lastPage).map((item) =>
          typeof item === "string" ? (
            <span className="gap" key={item} aria-hidden="true">…</span>
          ) : (
            <button
              type="button"
              key={item}
              className={item === page ? "active" : ""}
              disabled={disabled}
              onClick={() => onPage?.(item)}
              aria-current={item === page ? "page" : undefined}
            >
              {item}
            </button>
          ),
        )}
        <button
          type="button"
          disabled={disabled || page >= lastPage}
          onClick={() => onPage?.(page + 1)}
          aria-label="Next page"
        >
          <ChevronRight size={16} />
        </button>
      </nav>
    </div>
  );
}
