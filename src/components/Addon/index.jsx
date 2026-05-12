// source
import { useAddons } from "../../hooks/useGeneral";
import Bridges from "../Bridges";
import useTab from "../../hooks/useTab";

const { PanelRow } = wp.components;
const { useEffect, useMemo } = wp.element;

export default function Addon() {
  const [name] = useTab();
  const [addons] = useAddons();

  const logo = useMemo(() => {
    return addons.find((addon) => addon.name === name)?.logo;
  }, [name, addons]);

  useEffect(() => {
    if (!logo) return;

    const img = document.querySelector(`#${name} .addon-logo`);
    if (!img) return;

    img.setAttribute("src", logo);
    img.style.width = "auto";
    img.style.height = "25px";
  }, [name, logo]);

  return (
    <PanelRow>
      <Bridges />
    </PanelRow>
  );
}
