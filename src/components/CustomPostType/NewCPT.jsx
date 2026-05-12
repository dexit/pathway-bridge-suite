// source
import { usePostTypes } from "../../hooks/useGeneral";
import CPTFields from "./Fields";

const { Button, __experimentalSpacer: Spacer } = wp.components;
const { useState, useMemo } = wp.element;
const { __ } = wp.i18n;

const DEFAULTS = {
  public: true,
  exclude_from_search: false,
  publicly_queryable: true,
  show_in_rest: true,
  taxonomies: "category,post_tag",
  supports: ["title", "excerpt", "thumbnail", "custom-fields"],
  show_ui: true,
  show_in_menu: true,
  show_in_admin_bar: true,
  show_in_nav_menus: true,
  capability_type: "post",
  map_meta_cap: true,
};

export default function NewCustomPostType({ add }) {
  const [postTypes] = usePostTypes();

  const [data, setData] = useState({ ...DEFAULTS });

  const nameConflict = useMemo(() => {
    if (!data.name) return false;
    return postTypes.includes(data.name);
  }, [postTypes, data.name]);

  const onClick = () => {
    window.__wppbInvalidated = true;

    setData({ ...DEFAULTS });
    add(data);
  };

  const disabled = !(
    data.name &&
    data.label &&
    data.singular_label &&
    !nameConflict
  );

  return (
    <div
      style={{
        padding: "calc(24px) calc(32px)",
        width: "calc(100% - 64px)",
        backgroundColor: "rgb(245, 245, 245)",
      }}
    >
      <CPTFields data={data} setData={setData} nameConflict={nameConflict} />
      <Spacer paddingY="calc(8px)" />
      <div style={{ display: "flex", gap: "0.5rem" }}>
        <Button
          variant="primary"
          onClick={() => onClick()}
          style={{ width: "100px", justifyContent: "center" }}
          disabled={disabled}
          __next40pxDefaultSize
        >
          {__("Add", "posts-bridge")}
        </Button>
      </div>
    </div>
  );
}
