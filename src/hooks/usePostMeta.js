const { useState, useEffect, useCallback, useRef } = wp.element;
const apiFetch = wp.apiFetch;

export function usePostMeta(type) {
  const [fields, setFields] = useState([]);

  const timeout = useRef();
  const fetchController = useRef();
  const fetchMeta = useCallback(() => {
    clearTimeout(timeout.current);

    if (fetchController.current?.signal.aborted === false) {
      fetchController.current.abort();
    }

    if (!type) return Promise.resolve({});

    fetchController.current = new AbortController();
    return new Promise((res, rej) => {
      timeout.current = setTimeout(() => {
        apiFetch({
          path: `posts-bridge/v1/post_types/${type}/meta`,
          signal: fetchController.signal,
        })
          .then(res)
          .catch(rej);
      }, 300);
    });
  }, [type]);

  useEffect(() => {
    setFields([]);
    fetchMeta()
      .then((data) => {
        data = Array.isArray(data) ? data : [];
        setFields(data);
      })
      .catch(() => setFields([]));
  }, [fetchMeta]);

  return fields;
}
