import { useCallback, useEffect, useRef, useState } from "react";

export function useToasts() {
  const [toasts, setToasts] = useState([]);
  const timers = useRef(new Map());

  const dismiss = useCallback((id) => {
    setToasts((items) => items.filter((item) => item.id !== id));
    const timer = timers.current.get(id);
    if (timer) window.clearTimeout(timer);
    timers.current.delete(id);
  }, []);

  const push = useCallback((message, tone = "success") => {
    const id = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    setToasts((items) => [...items.slice(-2), { id, message, tone }]);
    const timer = window.setTimeout(() => {
      setToasts((items) => items.filter((item) => item.id !== id));
      timers.current.delete(id);
    }, 4200);
    timers.current.set(id, timer);
    return id;
  }, []);

  useEffect(() => () => {
    timers.current.forEach((timer) => window.clearTimeout(timer));
    timers.current.clear();
  }, []);

  return { toasts, push, dismiss };
}
