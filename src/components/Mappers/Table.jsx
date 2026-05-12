// source
import JsonFinger from "../../lib/JsonFinger";
import { useApiFields } from "../../providers/ApiSchema";
import DropdownSelect from "../DropdownSelect";

const { useMemo, useState } = wp.element;
const { TextControl, BaseControl, Button } = wp.components;
const { __ } = wp.i18n;

const INVALID_TO_STYLE = {
  "--wp-components-color-accent": "#cc1818",
  "color":
    "var(--wp-components-color-accent, var(--wp-admin-theme-color, #3858e9))",
  "borderColor":
    "var(--wp-components-color-accent, var(--wp-admin-theme-color, #3858e9))",
};

function useInputStyle(name = "") {
  const inputStyle = {
    height: "40px",
    paddingLeft: "12px",
    paddingRight: "12px",
    fontSize: "13px",
    borderRadius: "2px",
    width: "100%",
    display: "block",
  };

  if (name.length && (!JsonFinger.validate(name) || /\[\]/.test(name))) {
    return { ...inputStyle, ...INVALID_TO_STYLE };
  }

  return inputStyle;
}

export default function MappersTable({ title, mappers, setMappers }) {
  const apiFields = useApiFields();

  const apiFieldOptions = useMemo(() => {
    return apiFields.map((field) => ({
      value: field.name,
      label: `${field.name} | ${field.schema.type}`,
    }));
  }, [apiFields]);

  const setMapper = (index, attr, value) => {
    const newMappers = mappers
      .slice(0, index)
      .concat({ ...mappers[index], [attr]: value })
      .concat(mappers.slice(index + 1));

    setMappers(newMappers);
  };

  const dropMapper = (index) => {
    const newMappers = mappers.slice(0, index).concat(mappers.slice(index + 1));
    setMappers(newMappers);
  };

  const addMapper = (index) => {
    const newMappers = mappers
      .slice(0, index)
      .concat({ name: "", foreign: "" })
      .concat(mappers.slice(index));

    setMappers(newMappers);
  };

  const [fieldSelector, setFieldSelector] = useState(-1);

  let customOffset = 0;

  const tableName = title.toLowerCase().replace(" ", "-");
  return (
    <>
      <div style={{ display: "flex" }}>
        <label
          className="components-base-control__label"
          style={{
            fontSize: "11px",
            textTransform: "uppercase",
            fontWeight: 500,
            marginBottom: "calc(8px)",
          }}
        >
          {title}
        </label>
      </div>
      <datalist id={tableName + "-datalist-mappers-api-fields"}>
        {apiFields.map((f) => (
          <option key={tableName + f.name} value={f.name} />
        ))}
      </datalist>

      <table
        style={{
          width: "calc(100% + 10px)",
          minWidth: "500px",
          borderSpacing: "5px",
          margin: "0 -5px",
        }}
      >
        <colgroup>
          <col span="1" style={{ width: "clamp(150px, 15vw, 300px)" }} />
          <col span="1" style={{ width: "auto" }} />
          <col span="1" style={{ width: "85px" }} />
        </colgroup>
        <tbody>
          {mappers.map(
            ({ name, foreign, isCustom, label, datalist = [] }, index) => {
              if (!isCustom) customOffset++;

              return (
                <tr key={index}>
                  <td>
                    {(!isCustom && <b>{label}</b>) || (
                      <>
                        <datalist id={"datalist-" + name + "-" + index}>
                          {datalist.map((val) => (
                            <option key={tableName + val} value={val} />
                          ))}
                        </datalist>
                        <TextControl
                          placeholder={__("Name", "posts-bridge")}
                          value={name}
                          onChange={(value) => setMapper(index, "name", value)}
                          list={"datalist-" + name + "-" + index}
                          __next40pxDefaultSize
                          __nextHasNoMarginBottom
                        />
                      </>
                    )}
                  </td>
                  <td>
                    <div style={{ display: "flex" }}>
                      <div style={{ flex: 1 }}>
                        <BaseControl
                          __nextHasNoMarginBottom
                          __next40pxDefaultSize
                        >
                          <input
                            type="text"
                            placeholder={__("Foreign field", "posts-bridge")}
                            value={foreign || ""}
                            onChange={(ev) =>
                              setMapper(index, "foreign", ev.target.value)
                            }
                            style={useInputStyle(foreign)}
                            list={tableName + "-datalist-mappers-api-fields"}
                          />
                        </BaseControl>
                      </div>
                      <Button
                        style={{
                          height: "40px",
                          width: "40px",
                          justifyContent: "center",
                          marginLeft: "2px",
                        }}
                        disabled={apiFieldOptions.length === 0}
                        variant="secondary"
                        onClick={() => setFieldSelector(index)}
                        __next40pxDefaultSize
                      >
                        {"{...}"}
                        <DropdownSelect
                          open={fieldSelector === index}
                          title={__("Fields", "posts-bridge")}
                          tags={apiFieldOptions}
                          onChange={(value) => {
                            setFieldSelector(-1);
                            setMapper(index, "foreign", value);
                          }}
                          onRequestClose={() => setFieldSelector(-1)}
                        />
                      </Button>
                    </div>
                  </td>
                  <td>
                    <div
                      style={{
                        display: "flex",
                        marginLeft: "0.45em",
                        gap: "0.45em",
                      }}
                    >
                      <Button
                        size="compact"
                        variant="secondary"
                        disabled={!isCustom || !(name && foreign)}
                        onClick={() => addMapper(index + 1)}
                        style={{
                          width: "40px",
                          height: "40px",
                          justifyContent: "center",
                        }}
                        __next40pxDefaultSize
                      >
                        +
                      </Button>
                      <Button
                        disabled={
                          !isCustom ||
                          (index - customOffset === 0 && !(name || foreign))
                        }
                        variant="secondary"
                        onClick={() => dropMapper(index)}
                        style={{
                          width: "40px",
                          height: "40px",
                          justifyContent: "center",
                        }}
                        isDestructive
                        __next40pxDefaultSize
                      >
                        -
                      </Button>
                    </div>
                  </td>
                </tr>
              );
            }
          )}
        </tbody>
      </table>
    </>
  );
}
