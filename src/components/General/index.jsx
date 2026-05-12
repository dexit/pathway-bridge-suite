// source
import useGeneral from "../../hooks/useGeneral";
import Synchronize from "../Synchronize";
import Addons from "../Addons";
import Logger from "../Logger";
import Exporter from "../Exporter";

const { PanelBody, __experimentalSpacer: Spacer } = wp.components;
const { useEffect } = wp.element;
const { __ } = wp.i18n;

export default function GeneralSettings() {
  const [{ whitelist, synchronize, addons, debug, post_types }, setGeneral] =
    useGeneral();

  const update = (field) =>
    setGeneral({
      synchronize,
      whitelist,
      addons,
      debug,
      post_types,
      ...field,
    });

  useEffect(() => {
    const img = document.querySelector("#general .addon-logo");
    if (!img) return;
    img.removeAttribute("src");
  }, []);

  return (
    <>
      <Synchronize
        synchronize={synchronize}
        setSynchronize={(synchronize) => update({ synchronize })}
      />
      <Spacer paddingY="calc(8px)" />
      <Addons />
      <Logger />
      <PanelBody
        title={__("Import / Export", "posts-bridge")}
        initialOpen={false}
      >
        <Exporter />
      </PanelBody>
      <PanelBody title={__("Credits", "posts-bridge")} initialOpen={false}>
        <ul>
          <li>
            🏠{" "}
            <a href="https://postsbridge.codeccoop.org" target="_blank">
              {__("Website", "posts-bridge")}
            </a>
          </li>
          <li>
            📔{" "}
            <a
              href="https://postsbridge.codeccoop.org/documentation/"
              target="_blank"
            >
              {__("Documentation", "posts-bridge")}
            </a>
          </li>
          <li>
            💬{" "}
            <a
              href="https://wordpress.org/support/plugin/posts-bridge/"
              target="_blank"
            >
              {__("Support", "posts-bridge")}
            </a>
          </li>
          <li>
            💵{" "}
            <a href="https://buymeacoffee.com/codeccoop" target="_blank">
              {__("Donate", "posts-bridge")}
            </a>
          </li>
        </ul>
        <p>
          <strong>Posts Bridge</strong> has been created by{" "}
          <a href="https://www.codeccoop.org" target="_blank">
            Còdec
          </a>
          , a cooperative web development studio based on Barcelona.
        </p>
        <p>
          Please rate our plugin on{" "}
          <a
            href="https://wordpress.org/support/plugin/posts-bridge/reviews/?new-post"
            target="_blank"
          >
            WordPress.org
          </a>{" "}
          and help us to maintain this plugin alive 💖
        </p>
      </PanelBody>
    </>
  );
}
