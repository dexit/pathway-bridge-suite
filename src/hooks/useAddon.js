import { useAddons } from "../providers/Settings";
import useTab from "./useTab";

const { useMemo } = wp.element;

const DEFAULT = {
  title: "",
  bridges: [],
};

export default function useAddon() {
  const [addon] = useTab();
  const [addons, setAddons] = useAddons();

  const data = useMemo(() => {
    if (!addons[addon]) return DEFAULT;
    return { ...DEFAULT, ...addons[addon] };
  }, [addons, addon]);

  return [data, (data) => setAddons({ [addon]: data })];
}

export function useBridges() {
  const [addon, setAddon] = useAddon();
  return [addon.bridges || [], (bridges) => setAddon({ ...addon, bridges })];
}

export function useRemoteCPTs() {
  const [bridges] = useBridges();

  return useMemo(() => {
    return new Set(bridges.map((b) => b.post_type));
  }, [bridges]);
}
