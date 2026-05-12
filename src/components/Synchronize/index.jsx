// source
import useAjaxSync from "../../hooks/useAjaxSync";
import { useError } from "../../providers/Error";
import { useLoading } from "../../providers/Loading";
import ToggleControl from "../Toggle";

const {
  __experimentalSpacer: Spacer,
  PanelRow,
  SelectControl,
  Button,
} = wp.components;
const { __ } = wp.i18n;

const recurrenceOptions = [
  {
    label: __("Quarterly", "posts-bridge"),
    value: "pb-quarterly",
  },
  {
    label: __("Twice Hourly", "posts-bridge"),
    value: "pb-twicehourly",
  },
  {
    label: __("Hourly", "posts-bridge"),
    value: "hourly",
  },
  {
    label: __("Twice Daily", "posts-bridge"),
    value: "pb-twicedaily",
  },
  {
    label: __("Daily", "posts-bridge"),
    value: "daily",
  },
  {
    label: __("Weekly", "posts-bridge"),
    value: "weekly",
  },
];

export default function Synchronize({ synchronize, setSynchronize }) {
  const [loading] = useLoading();
  const [error] = useError();

  const { enabled, recurrence } = synchronize;

  const sync = useAjaxSync();

  const update = (field) => setSynchronize({ ...synchronize, ...field });

  return (
    <>
      <Spacer paddingY="calc(3px)" />
      <PanelRow>
        <Button
          variant="primary"
          disabled={loading || error}
          onClick={sync}
          style={{ width: "150px", justifyContent: "center" }}
          __next40pxDefaultSize
        >
          {__("Synchronize", "posts-bridge")}
        </Button>
      </PanelRow>
      <Spacer paddingY="calc(8px)" />
      <hr />
      <p>{__("Schedule", "posts-bridge")}</p>
      <PanelRow>
        <ToggleControl
          label={__("Automatic syncrhonization", "posts-bridge")}
          help={__(
            "Allow scheduled pull strategy syncrhonization. WordPress will check the remote sources for updates and update its indices. This strategy can cause performance issues if you have large backend models collections",
            "posts-bridge"
          )}
          checked={enabled}
          onChange={() => update({ enabled: !enabled })}
          __nextHasNoMarginBottom
        />
      </PanelRow>
      <Spacer paddingY="calc(8px)" />
      <PanelRow>
        <SelectControl
          label={__("Recurrence", "posts-bridge")}
          value={recurrence}
          onChange={(recurrence) => update({ recurrence })}
          options={recurrenceOptions.map((opt) => ({
            value: opt.value,
            label: __(opt.label, "posts-bridge"),
          }))}
          __nextHasNoMarginBottom
          __next40pxDefaultSize
          style={{ width: "150px" }}
        />
      </PanelRow>
    </>
  );
}
