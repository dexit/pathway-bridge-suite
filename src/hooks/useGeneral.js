import { useGeneral } from "../providers/Settings";

export default useGeneral;

function updateRegistry(from, to) {
  return from.map((item) => {
    const enabled = !!to.find(({ name }) => name === item.name)?.enabled;
    return { ...item, enabled };
  });
}

export function useAddons() {
  const [general, setGeneral] = useGeneral();

  return [
    general.addons || [],
    (addons) => {
      setGeneral({
        ...general,
        addons: updateRegistry(general.addons || [], addons),
      });
    },
  ];
}

export function usePostTypes() {
  const [general, setGeneral] = useGeneral();
  return [
    general.post_types || [],
    (post_types) => {
      setGeneral({
        ...general,
        post_types,
      });
    },
  ];
}

export function useDebug() {
  const [general, setGeneral] = useGeneral();
  return [
    general.debug,
    (debug) => {
      window.__wppbInvalidated = true;
      setGeneral({ ...general, debug });
    },
  ];
}
