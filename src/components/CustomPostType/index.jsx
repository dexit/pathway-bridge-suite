// source
import { usePostTypes } from "../../hooks/useGeneral";
import { useCustomPostTypeData } from "../../providers/CustomPostTypes";
import CPTFields from "./Fields";
import RemoveButton from "../RemoveButton";

const { Button, __experimentalSpacer: Spacer } = wp.components;
const { useState, useEffect, useMemo } = wp.element;
const { __ } = wp.i18n;

export default function CustomPostType({ name, update, remove }) {
  const [postTypes] = usePostTypes();

  const data = useCustomPostTypeData(name);
  const [state, setState] = useState({ name });

  const nameConflict = useMemo(() => {
    if (!data?.name) return false;

    return state.name !== data.name && postTypes.includes(state.name);
  }, [postTypes, data, state.name]);

  useEffect(() => {
    if (!data) return;
    setState(JSON.parse(JSON.stringify(data)));
  }, [data]);

  if (!data) return <p>Loading</p>;

  return (
    <div
      style={{
        padding: "calc(24px) calc(32px)",
        width: "calc(100% - 64px)",
        backgroundColor: "rgb(245, 245, 245)",
      }}
    >
      <CPTFields data={state} setData={setState} nameConflict={nameConflict} />
      <Spacer paddingY="calc(8px)" />
      <div
        style={{
          display: "flex",
          gap: "0.5em",
        }}
      >
        <Button
          disabled={nameConflict}
          variant="primary"
          onClick={() => update(state)}
          style={{ width: "100px", justifyContent: "center" }}
          __next40pxDefaultSize
        >
          {__("Save", "posts-bridge")}
        </Button>
        <RemoveButton
          disabled={nameConflict}
          onClick={() => remove(data.name)}
          icon
        />
      </div>
    </div>
  );
}
