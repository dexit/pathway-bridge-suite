import ToggleControl from "../Toggle";
import { useTaxonomies } from "../../hooks/useTaxonomies";
import { CustomFields } from "./CustomFields";

const {
  PanelBody,
  TextControl,
  SelectControl,
  __experimentalSpacer: Spacer,
} = wp.components;
const { useState, useEffect, useMemo } = wp.element;
const { __ } = wp.i18n;

const SUPPORTS_OPTIONS = [
  {
    label: __("Title", "posts-bridge"),
    value: "title",
  },
  {
    label: __("Editor", "posts-bridge"),
    value: "editor",
  },
  {
    label: __("Comments", "posts-bridge"),
    value: "comments",
  },
  {
    label: __("Revisions", "posts-bridge"),
    value: "revisions",
  },
  {
    label: __("Trackbacks", "posts-bridge"),
    value: "trackbacks",
  },
  {
    label: __("Author", "posts-bridge"),
    value: "author",
  },
  {
    label: __("Excerpt", "posts-bridge"),
    value: "excerpt",
  },
  {
    label: __("Thumbnail", "posts-bridge"),
    value: "thumbnail",
  },
  {
    label: __("Page attributes", "posts-bridge"),
    value: "page-attributes",
  },
  {
    label: __("Custom fields", "posts-bridge"),
    value: "custom-fields",
  },
  {
    label: __("Post formats", "posts-bridge"),
    value: "post-formats",
  },
];

export default function CPTFields({ data, setData, nameConflict }) {
  const handleSetName = (name) => {
    name = name.toLowerCase().replace(/\s+/, "_");
    setData({ ...data, name });
  };

  const taxonomies = useTaxonomies(data.name);
  const [taxSelection, setTaxSelection] = useState([]);

  const taxOptions = useMemo(() => {
    if (!taxonomies.length) return [{ value: "", label: "" }];
    return taxonomies.map((tax) => ({ value: tax.slug, label: tax.name }));
  }, [taxonomies]);

  useEffect(() => {
    setData({ ...data, taxonomies: taxSelection.join(",") });
  }, [taxSelection]);

  useEffect(() => {
    const newTaxSelection = taxSelection.filter((slug) =>
      taxonomies.find((tax) => slug === tax.slug)
    );

    setTaxSelection(newTaxSelection);
  }, [taxonomies]);

  return (
    <>
      <div style={{ display: "flex", gap: "0.5em" }}>
        <TextControl
          label={__("Name", "posts-bridge")}
          help={
            nameConflict
              ? __("This name is already in use", "posts-bridge")
              : ""
          }
          value={data.name}
          onChange={handleSetName}
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
        <TextControl
          label={__("Label", "posts-bridge")}
          value={data.label}
          onChange={(label) => setData({ ...data, label })}
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
        <TextControl
          label={__("Singular label", "posts-bridge")}
          value={data.singular_label}
          onChange={(singular_label) => setData({ ...data, singular_label })}
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
      </div>
      <Spacer />
      <PanelBody title={__("Visibility", "posts-bridge")} initialOpen={false}>
        <Spacer paddingY="calc(4px)" />
        <div
          style={{
            display: "flex",
            gap: "1em",
          }}
        >
          <ToggleControl
            label={__("Public", "posts-bridge")}
            checked={data.public}
            onChange={() => setData({ ...data, public: !data.public })}
            __nextHasNoMarginBottom
          />
          <ToggleControl
            label={__("Exclude from search", "posts-bridge")}
            checked={data.exclude_from_search}
            onChange={() =>
              setData({
                ...data,
                exclude_from_search: !data.exclude_from_search,
              })
            }
            __nextHasNoMarginBottom
          />
          <ToggleControl
            label={__("Publicly queryable", "posts-bridge")}
            checked={data.publicly_queryable}
            onChange={() =>
              setData({
                ...data,
                publicly_queryable: !data.publicly_queryable,
              })
            }
            __nextHasNoMarginBottom
          />
          <ToggleControl
            label={__("Show in REST", "posts-bridge")}
            checked={data.show_in_rest}
            onChange={() =>
              setData({ ...data, show_in_rest: !data.show_in_rest })
            }
            __nextHasNoMarginBottom
          />
        </div>
      </PanelBody>
      <PanelBody title={__("Attributes", "posts-bridge")} initialOpen={false}>
        <Spacer paddingY="calc(4px)" />
        <div
          style={{
            display: "flex",
            alignItems: "end",
            gap: "1em",
          }}
        >
          <SelectControl
            multiple
            label={__("Taxonomies", "posts-bridge")}
            value={taxSelection}
            onChange={(taxSelection) => setTaxSelection(taxSelection)}
            options={taxOptions}
            style={{ height: "200px" }}
            __nextHasNoMarginBottom
          />
          <SelectControl
            multiple
            label={__("Supports", "posts-bridge")}
            value={data.supports}
            onChange={(supports) => setData({ ...data, supports })}
            options={SUPPORTS_OPTIONS}
            style={{ height: "200px" }}
            __nextHasNoMarginBottom
          />
        </div>
      </PanelBody>
      <PanelBody title={__("Admin", "posts-bridge")} initialOpen={false}>
        <Spacer paddingY="calc(4px)" />
        <div
          style={{
            display: "flex",
            gap: "1em",
          }}
        >
          <ToggleControl
            label={__("Show UI", "posts-bridge")}
            checked={data.show_ui}
            onChange={() => setData({ ...data, show_ui: !data.show_ui })}
            __nextHasNoMarginBottom
          />
          <ToggleControl
            label={__("Show in menu", "posts-bridge")}
            checked={data.show_in_menu}
            onChange={() =>
              setData({ ...data, show_in_menu: !data.show_in_menu })
            }
            __nextHasNoMarginBottom
          />
          <ToggleControl
            label={__("Show in admin bar", "posts-bridge")}
            checked={data.show_in_admin_bar}
            onChange={() =>
              setData({
                ...data,
                show_in_admin_bar: !data.show_in_admin_bar,
              })
            }
            __nextHasNoMarginBottom
          />
          <ToggleControl
            label={__("Show in nav menus", "posts-bridge")}
            checked={data.show_in_nav_menus}
            onChange={() =>
              setData({
                ...data,
                show_in_nav_menus: !data.show_in_nav_menus,
              })
            }
            __nextHasNoMarginBottom
          />
        </div>
      </PanelBody>
      <PanelBody title={__("URL", "posts-bridge")} initialOpen={false}>
        <Spacer paddingY="calc(4px)" />
        <div
          style={{
            display: "flex",
            alignItems: "end",
            gap: "1em",
          }}
        >
          <TextControl
            label={__("Query var", "posts-bridge")}
            value={data.query_var}
            onChange={(query_var) => setData({ ...data, query_var })}
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
          <TextControl
            label={__("Rewrite slug", "posts-bridge")}
            value={data.rewrite}
            onChange={(rewrite) => setData({ ...data, rewrite })}
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
          <TextControl
            label={__("REST base", "posts-bridge")}
            value={data.rest_base}
            onChange={(rest_base) => setData({ ...data, rest_base })}
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
          <div style={{ paddingBottom: "1em" }}>
            <ToggleControl
              label={__("Has archive", "posts-bridge")}
              checked={data.has_archive}
              onChange={() =>
                setData({ ...data, has_archive: !data.has_archive })
              }
              __nextHasNoMarginBottom
            />
          </div>
        </div>
      </PanelBody>
      <PanelBody title={__("Capabilities", "posts-bridge")} initialOpen={false}>
        <Spacer paddingY="calc(4px)" />
        <div
          style={{
            display: "flex",
            alignItems: "end",
            gap: "1em",
          }}
        >
          <TextControl
            label={__("Capability type", "posts-bridge")}
            value={data.capability_type}
            onChange={(capability_type) =>
              setData({ ...data, capability_type })
            }
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
          <div style={{ paddingBottom: "1em" }}>
            <ToggleControl
              label={__("Map meta capabilities", "posts-bridge")}
              checked={data.map_meta_cap}
              onChange={() =>
                setData({ ...data, map_meta_cap: !data.map_meta_cap })
              }
              __nextHasNoMarginBottom
            />
          </div>
        </div>
      </PanelBody>
      <PanelBody
        title={__("Custom fields", "posts-bridge")}
        initialOpen={false}
      >
        <CustomFields
          fields={data.meta}
          setFields={(meta) => setData({ ...data, meta })}
        />
      </PanelBody>
    </>
  );
}
