import ToggleControl from "../Toggle";

const { useState } = wp.element;
const { __ } = wp.i18n;
const { TextControl, SelectControl, Button } = wp.components;

const TYPE_OPTIONS = [
  { value: "string", label: "string" },
  { value: "integer", label: "integer" },
  { value: "number", label: "number" },
  { value: "boolean", label: "boolean" },
  { value: "array", label: "array" },
  { value: "object", label: "object" },
];

export function CustomFields({ fields = [], setFields }) {
  const [newField, setNewField] = useState({
    name: "",
    type: "string",
    default: "",
    single: true,
    show_in_rest: true,
  });

  const addNewField = (field) => {
    setFields(fields.concat(field));
    setNewField({
      name: "",
      type: "string",
      default: "",
      single: true,
      show_in_rest: true,
    });
  };

  const removeField = (index) => {
    const newFields = fields.filter((_, i) => i !== index);

    setFields(newFields);
  };

  const updateField = (index, field) => {
    const newFields = fields
      .slice(0, index)
      .concat([field])
      .concat(fields.slice(index + 1));

    setFields(newFields);
  };

  return (
    <table
      style={{
        width: "calc(100% + 10px)",
        margin: "0 -5px",
        borderSpacing: "5px",
      }}
    >
      <thead>
        <tr>
          <th
            scope="col"
            style={{
              textAlign: "left",
              padding: "1em 0 0 0.5em",
              columnWidth: "200px",
            }}
          >
            {__("Name", "forms-bridge")}
          </th>
          <th
            scope="col"
            style={{
              textAlign: "left",
              padding: "1em 0 0 0.5em",
              columnWidth: "200px",
            }}
          >
            {__("Type", "forms-bridge")}
          </th>
          <th
            scope="col"
            style={{
              textAlign: "left",
              padding: "1em 0 0 0.5em",
              columnWidth: "200px",
            }}
          >
            {__("Default", "forms-bridge")}
          </th>
          <th
            scope="col"
            style={{
              textAlign: "left",
              padding: "1em 0 0 0.5em",
            }}
          >
            {__("Single", "forms-bridge")}
          </th>
          <th
            scope="col"
            style={{
              textAlign: "left",
              padding: "1em 0 0 0.5em",
            }}
          >
            {__("Show in REST", "forms-bridge")}
          </th>
          <th aria-hidden="true"></th>
        </tr>
      </thead>
      <tbody>
        {fields.map((field, index) => (
          <TableRow
            key={index}
            field={field}
            setField={(field) => updateField(index, field)}
            remove={() => removeField(index)}
          />
        ))}
        <TableRow field={newField} setField={setNewField} add={addNewField} />
      </tbody>
    </table>
  );
}

function TableRow({ field, setField, add, remove }) {
  return (
    <tr>
      <td style={{ columnWidth: "200px" }}>
        <TextControl
          value={field.name}
          onChange={(name) => setField({ ...field, name })}
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
      </td>
      <td style={{ columnWidth: "200px" }}>
        <SelectControl
          value={field.type}
          onChange={(type) => setField({ ...field, type })}
          options={TYPE_OPTIONS}
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
      </td>
      <td style={{ columnWidth: "200px" }}>
        <TextControl
          value={field.default}
          onChange={(value) => setField({ ...field, default: value })}
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
      </td>
      <td>
        <div
          style={{
            height: "40px",
            width: "60px",
            display: "flex",
            alignItems: "center",
            marginRight: "calc(-8px)",
            paddingLeft: "calc(5px)",
          }}
        >
          <ToggleControl
            checked={field.single}
            onChange={() => setField({ ...field, single: !field.single })}
            __nextHasNoMarginBottom
          />
        </div>
      </td>
      <td>
        <div
          style={{
            height: "40px",
            width: "60px",
            display: "flex",
            alignItems: "center",
            marginRight: "calc(-8px)",
            paddingLeft: "calc(5px)",
          }}
        >
          <ToggleControl
            checked={field.show_in_rest}
            onChange={() =>
              setField({
                ...field,
                show_in_rest: !field.show_in_rest,
              })
            }
            __nextHasNoMarginBottom
          />
        </div>
      </td>
      <td>
        {(add && (
          <Button
            disabled={!field.name}
            size="compact"
            variant="secondary"
            style={{
              height: "40px",
              width: "40px",
              justifyContent: "center",
              marginLeft: "2px",
            }}
            onClick={() => add(field)}
            __next40pxDefaultSize
          >
            +
          </Button>
        )) || (
          <Button
            size="compact"
            variant="secondary"
            style={{
              height: "40px",
              width: "40px",
              justifyContent: "center",
              marginLeft: "2px",
            }}
            onClick={() => remove(field)}
            isDestructive
            __next40pxDefaultSize
          >
            -
          </Button>
        )}
      </td>
    </tr>
  );
}
