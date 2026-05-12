const { useState, useEffect, useCallback, useRef } = wp.element;
const apiFetch = wp.apiFetch;
const { addQueryArgs } = wp.url;

const DEFAULTS = [
  { name: "Tags", slug: "post_tag" },
  { name: "Categories", slug: "category" },
];

export function useTaxonomies(type) {
  const [taxonomies, setTaxonomies] = useState(DEFAULTS);

  const timeout = useRef();
  const fetchController = useRef();
  const fetchTaxonomies = useCallback(() => {
    clearTimeout(timeout.current);

    if (fetchController.current?.signal.aborted === false) {
      fetchController.current.abort();
    }

    fetchController.current = new AbortController();
    return new Promise((res, rej) => {
      timeout.current = setTimeout(() => {
        apiFetch({
          path: addQueryArgs("wp/v2/taxonomies", { type }),
          signal: fetchController.signal,
        })
          .then(res)
          .catch(rej);
      }, 300);
    });
  }, [type]);

  useEffect(() => {
    setTaxonomies(DEFAULTS);
    fetchTaxonomies()
      .then((data) => {
        const taxonomies = Object.values(data)
          .filter((tax) => {
            return (
              [
                "nav_menu",
                "wp_pattern_category",
                "post_tag",
                "category",
              ].indexOf(tax.slug) === -1
            );
          })
          .concat(DEFAULTS);

        setTaxonomies(taxonomies);
      })
      .catch(() => setTaxonomies(DEFAULTS));
  }, [fetchTaxonomies]);

  return taxonomies;
}
