// source
import useRemoteCPTs from "../../hooks/useRemoteCPTs";
import { usePostTypes } from "../../hooks/useGeneral";
import { useBackends } from "../../hooks/useHttp";
import { isset, prependEmptyOption } from "../../lib/utils";
import FieldWrapper from "../FieldWrapper";
import { useApiEndpoints } from "../../providers/ApiSchema";

const { BaseControl, SelectControl } = wp.components;
const { useEffect, useMemo } = wp.element;

export const INTERNALS = [
  "enabled",
  "is_valid",
  "field_mappers",
  "tax_mappers",
];

const ORDER = ["name", "backend", "endpoint", "method"];

export default function BridgeFields({ data, setData, schema, errors = {} }) {
  const endpoints = useApiEndpoints();
  const [postTypes] = usePostTypes();
  const rcpts = useRemoteCPTs();

  const availablePostTypes = useMemo(() => {
    return postTypes.filter((pt) => !rcpts.has(pt)).sort();
  }, [rcpts, postTypes]);

  const postTypeOptions = useMemo(() => {
    if (postTypes.length === 0) return [{ label: "", value: "" }];

    return availablePostTypes
      .filter((pt) => pt !== data.post_type)
      .concat([data.post_type].filter((p) => p))
      .sort()
      .map((postType) => ({
        label: postType,
        value: postType,
      }));
  }, [data.post_type, availablePostTypes]);

  const [backends] = useBackends();
  const backendOptions = useMemo(() => {
    if (!backends.length) return [{ label: "", value: "" }];

    return backends
      .map(({ name }) => ({
        label: name,
        value: name,
      }))
      .sort((a, b) => {
        return a.label > b.label ? 1 : -1;
      });
  }, [backends]);

  const fields = useMemo(() => {
    if (!schema) return [];

    return Object.keys(schema.properties)
      .filter((name) => !INTERNALS.includes(name))
      .map((name) => ({
        ...schema.properties[name],
        label: schema.properties[name].title || name,
        name,
        value: schema.properties[name].const,
      }))
      .map((field) => {
        if (field.name === "post_type") {
          return {
            ...field,
            type: "select",
            options: postTypeOptions,
          };
        } else if (field.name === "backend") {
          return {
            ...field,
            type: "select",
            options: backendOptions,
          };
        } else if (field.enum) {
          return {
            ...field,
            type: "select",
            options: field.enum.map((value) => ({ label: value, value })),
          };
        }

        return field;
      });
  }, [schema, backendOptions, postTypeOptions]);

  useEffect(() => {
    const defaults = fields.reduce((defaults, field) => {
      if (field.default && !isset(data, field.name)) {
        defaults[field.name] = field.default;
      } else if (field.value && field.value !== data[field.name]) {
        defaults[field.name] = field.value;
      } else if (field.type === "select") {
        if (!field.options.length && data[field.name]) {
          defaults[field.name] = "";
        } else if (!data[field.name] || field.options.length === 1) {
          const value = field.options[0]?.value || "";
          if (value !== data[field.name]) {
            defaults[field.name] = value;
          }
        }
      } else if (field.enum && field.enum.length === 1) {
        if (data[field.name] !== field.enum[0]) {
          defaults[field.name] = field.enum[0];
        }
      }

      if (!backends.length && data.backend) {
        defaults.backend = "";
      }

      if (!availablePostTypes.length) {
        if (data.post_type) {
          defaults.post_type = "";
        }
      } else if (rcpts.has(defaults.post_type)) {
        defaults.post_type = availablePostTypes[0];
      }

      return defaults;
    }, {});

    if (Object.keys(defaults).length) {
      setData({ ...data, ...defaults });
    }
  }, [data, fields, availablePostTypes]);

  return fields
    .filter((field) => !field.value)
    .sort((a, b) =>
      ORDER.includes(a.name) && ORDER.includes(b.name)
        ? ORDER.indexOf(a.name) - ORDER.indexOf(b.name)
        : 0
    )
    .map((field) => {
      switch (field.type) {
        case "string":
          return (
            <StringField
              key={field.name}
              error={errors[field.name]}
              label={field.label}
              value={data[field.name] || ""}
              setValue={(value) => setData({ ...data, [field.name]: value })}
              datalist={field.name === "endpoint" ? endpoints : []}
            />
          );
        case "select":
          return (
            <SelectField
              key={field.name}
              error={errors[field.name]}
              label={field.label}
              value={data[field.name] || ""}
              setValue={(value) => setData({ ...data, [field.name]: value })}
              options={field.options}
            />
          );
      }
    });
}

export function StringField({
  label,
  value,
  setValue,
  error,
  disabled,
  datalist = [],
}) {
  return (
    <FieldWrapper>
      <BaseControl label={label} help={error} __nextHasNoMarginBottom>
        <input
          name={label}
          type="text"
          list={"datalist-" + label}
          value={value}
          onChange={(ev) => setValue(ev.target.value)}
          disabled={disabled}
          style={{
            height: "40px",
            paddingRight: "12px",
            paddingLeft: "12px",
            borderColor: "var(--wp-components-color-gray-600,#949494)",
            fontSize: "13px",
            width: "100%",
            border: "1px solid #949494",
            borderRadius: "2px",
          }}
        />
        <datalist id={"datalist-" + label}>
          {datalist.map((endpoint) => (
            <option key={endpoint} value={endpoint}></option>
          ))}
        </datalist>
      </BaseControl>
    </FieldWrapper>
  );
}

export function SelectField({
  label,
  options,
  value,
  setValue,
  optional,
  error,
  disabled,
}) {
  if (optional) {
    options = prependEmptyOption(options);
  }

  return (
    <FieldWrapper>
      <SelectControl
        disabled={disabled}
        label={label}
        value={value}
        onChange={setValue}
        options={options}
        help={error}
        __nextHasNoMarginBottom
        __next40pxDefaultSize
      />
    </FieldWrapper>
  );
}
