// source
import { useError } from "../../providers/Error";
import { useLoading } from "../../providers/Loading";
import { useFetchSettings, useSettings } from "../../providers/Settings";
import { uploadJson } from "../../lib/utils";

const apiFetch = wp.apiFetch;
const { useState, useEffect, useCallback } = wp.element;
const { __experimentalSpacer: Spacer, Button, Modal } = wp.components;
const { __ } = wp.i18n;

export default function Exporter() {
  const [loading, setLoading] = useLoading();
  const [error, setError] = useError();
  const [settings, setSettings] = useSettings();
  const fetchSettings = useFetchSettings();

  const [showModal, setShowModal] = useState(false);
  const [userConsent, setUserConsent] = useState(false);

  const downloadConfig = useCallback(() => {
    const blob = new Blob([JSON.stringify(settings)], {
      type: "application/json",
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    const date = new Date();

    let year = String(date.getFullYear());
    let month = String(date.getMonth() + 1);
    if (month.length === 1) month = "0" + month;
    let day = String(date.getDate());
    if (day.length === 1) day = "0" + day;

    link.download = `${year}${month}${day}-posts-bridge.json`;
    link.href = url;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }, [settings]);

  const importConfig = useCallback(() => {
    uploadJson()
      .then((settings) => {
        setSettings(settings).catch(() => {
          setError(
            __("It has been an error with config import", "posts-bridge")
          );
        });
      })
      .catch((err) => {
        if (err.name === "SyntaxError") {
          setError(__("JSON syntax error", "posts-bridge"));
        } else {
          setError(
            __("Something went wrong with the file upload", "posts-bridge")
          );
        }
      });
  }, [setSettings]);

  const wipeConfig = useCallback(() => {
    setLoading(true);

    apiFetch({
      path: "posts-bridge/v1/settings",
      method: "DELETE",
    })
      .then(fetchSettings)
      .catch(() => {
        setError(__("Wipe config error", "posts-bridge"));
      })
      .finally(() => setLoading(false));
  }, [fetchSettings]);

  useEffect(() => {
    return () => {
      if (!showModal) return;
      setShowModal(false);

      if (!userConsent) return;
      if (showModal === "import") {
        importConfig();
      } else {
        wipeConfig();
      }

      setUserConsent(false);
    };
  }, [showModal, userConsent]);

  return (
    <>
      <p>
        {__(
          "Export or import your configuration as a JSON to migrate your bridges to, or from, any other WordPress instance",
          "posts-bridge"
        )}
      </p>
      <Spacer paddingBottom="5px" />
      <div style={{ display: "flex", gap: "0.5rem" }}>
        <Button
          disabled={!!error || loading}
          variant="secondary"
          description={__("Export Posts Bridge config as JSON", "posts-bridge")}
          onClick={downloadConfig}
          style={{ width: "150px", justifyContent: "center" }}
          __next40pxDefaultSize
        >
          {__("Download config", "posts-bridge")}
        </Button>
        <Button
          disabled={!!error || loading}
          variant="primary"
          description={__("Import Posts Bridge JSON config", "posts-bridge")}
          onClick={() => setShowModal("import")}
          style={{ width: "150px", justifyContent: "center" }}
          __next40pxDefaultSize
        >
          {__("Import config", "posts-bridge")}
        </Button>
        <Button
          disabled={!!error || loading}
          variant="primary"
          description={__("Wipe Posts Bridge settings", "posts-bridge")}
          onClick={() => setShowModal("wipe")}
          style={{ width: "150px", justifyContent: "center" }}
          isDestructive
          __next40pxDefaultSize
        >
          {__("Wipe config", "posts-bridge")}
        </Button>
      </div>
      {showModal === "import" && (
        <Modal
          title={__("Config import warning", "posts-bridge")}
          onRequestClose={() => setShowModal(false)}
          size="small"
        >
          <p>
            {__(
              "Import a new configuration is a destructive action. Your current configuration will be replaced with the new one. If there are some errors on the new config, Posts Bridge will filter it to avoid bugs.",
              "posts-bridge"
            )}
          </p>
          <p>{__("Are you sure to continue?", "posts-bridge")}</p>
          <div
            style={{ display: "flex", gap: "0.5rem", justifyContent: "center" }}
          >
            <Button
              variant="primary"
              description={__("Continue", "posts-bridge")}
              onClick={() => setUserConsent(true)}
            >
              {__("Continue", "posts-bridge")}
            </Button>
            <Button
              variant="primary"
              isDestructive={true}
              description={__("Cancel", "posts-bridge")}
              onClick={() => setUserConsent(false)}
            >
              {__("Cancel", "posts-bridge")}
            </Button>
          </div>
        </Modal>
      )}
      {showModal === "wipe" && (
        <Modal
          title={__("Wipe config warning", "posts-bridge")}
          onRequestClose={() => setShowModal(false)}
          size="small"
        >
          <p>
            {__(
              "You are going to wipe Posts Bridge config. After that, Posts Bridge will be reset to factory defaults. All your data will be lost.",
              "posts-bridge"
            )}
          </p>
          <p>{__("Are you sure to continue?", "posts-bridge")}</p>
          <div
            style={{ display: "flex", gap: "0.5rem", justifyContent: "center" }}
          >
            <Button
              variant="primary"
              description={__("Continue", "posts-bridge")}
              onClick={() => setUserConsent(true)}
            >
              {__("Continue", "posts-bridge")}
            </Button>
            <Button
              variant="primary"
              isDestructive={true}
              description={__("Cancel", "posts-bridge")}
              onClick={() => setUserConsent(false)}
            >
              {__("Cancel", "posts-bridge")}
            </Button>
          </div>
        </Modal>
      )}
    </>
  );
}
