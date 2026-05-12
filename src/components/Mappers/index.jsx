// source
import MappersTable from "./Table";
import { useTaxonomies } from "../../hooks/useTaxonomies";
import { usePostMeta } from "../../hooks/usePostMeta";

const { __experimentalSpacer: Spacer } = wp.components;
const { useMemo } = wp.element;
const { __ } = wp.i18n;

const MODEL = {
  post_title: __("Title", "posts-bridge"),
  post_name: __("Slug", "posts-bridge"),
  post_excerpt: __("Excerpt", "posts-bridge"),
  post_content: __("Content", "posts-bridge"),
  post_status: __("Status", "posts-bridge"),
  featured_media: __("Featured media", "posts-bridge"),
  post_date: __("Date", "posts-bridge"),
  post_date_gmt: __("Date GMT", "posts-bridge"),
  post_modified: __("Modified", "posts-bridge"),
  post_modified_gmt: __("Modified GMT", "posts-bridge"),
};

export default function Mappers({
  postType,
  fieldMappers,
  setFieldMappers,
  taxMappers,
  setTaxMappers,
}) {
  const metaFields = usePostMeta(postType);
  const datalistMeta = metaFields.map((field) => field.name);

  const fields = useMemo(() => {
    return Object.keys(MODEL)
      .map((name) => {
        return (
          fieldMappers.find((m) => m.name === name) || { name, foreign: "" }
        );
      })
      .map((m) => ({ ...m, isCustom: false, label: MODEL[m.name] }))
      .concat(
        fieldMappers
          .filter((m) => !MODEL[m.name])
          .map((m) => ({ ...m, isCustom: true }))
      );
  }, [fieldMappers]);

  const postFields = useMemo(() => {
    return fields.slice(0, Object.keys(MODEL).length);
  }, [fields]);

  const customFields = useMemo(() => {
    return fields
      .slice(Object.keys(MODEL).length)
      .map((field) => ({ ...field, datalist: datalistMeta }));
  }, [fields]);

  const postTaxonomies = useTaxonomies(postType);
  const taxonomies = useMemo(() => {
    return postTaxonomies.map((tax) => {
      return {
        name: tax.slug,
        foreign: taxMappers.find((m) => m.name === tax.slug)?.foreign || "",
        label: tax.name,
        isCustom: false,
      };
    });
  }, [postTaxonomies, taxMappers]);

  if (!customFields.length) {
    setFieldMappers(fieldMappers.concat({ name: "", foreign: "" }));
  }

  return (
    <div>
      <MappersTable
        title={__("Mappers", "posts-bridge")}
        mappers={postFields}
        setMappers={(postFields) =>
          setFieldMappers(
            postFields
              .concat(customFields)
              .map(({ name, foreign }) => ({ name, foreign }))
          )
        }
      />
      <Spacer paddingY="calc(3px)" />
      {(taxonomies.length && (
        <>
          <MappersTable
            title={__("Taxonomies", "posts-bridge")}
            mappers={taxonomies}
            setMappers={(taxonomies) =>
              setTaxMappers(
                taxonomies.map(({ name, foreign }) => ({ name, foreign }))
              )
            }
          />
          <Spacer paddingY="calc(3px)" />
        </>
      )) ||
        null}
      <MappersTable
        title={__("Custom fields", "posts-bridge")}
        mappers={customFields}
        setMappers={(customFields) =>
          setFieldMappers(
            postFields
              .concat(customFields)
              .map(({ name, foreign }) => ({ name, foreign }))
          )
        }
      />
    </div>
  );
}
