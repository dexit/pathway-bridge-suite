// source
import { useAddons } from "../providers/Settings";

const { useMemo } = wp.element;

export default function useRemoteCPTs() {
  const [addons] = useAddons();

  return useMemo(() => {
    const postTypes = Object.values(addons).reduce((postTypes, addon) => {
      addon.bridges.forEach((bridge) => {
        postTypes.push(bridge.post_type);
      });

      return postTypes;
    }, []);

    return new Set(postTypes);
  }, [addons]);
}
