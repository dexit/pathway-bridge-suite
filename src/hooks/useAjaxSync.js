// source
import { useError } from "../providers/Error";
import { useLoading } from "../providers/Loading";

const { __ } = wp.i18n;

export default function useAjaxSync(props = {}) {
  const { postType } = props;

  const [loading, setLoading] = useLoading();
  const [error, setError] = useError();

  const ping = () => {
    const body = new URLSearchParams();
    body.set("_ajax_nonce", window.postsBridgeAjaxSync.nonce);
    body.set("action", window.postsBridgeAjaxSync.actions.ping);

    return new Promise((res, rej) => {
      const ajax = new XMLHttpRequest();
      ajax.timeout = 1000 * 60;

      ajax.onreadystatechange = function () {
        if (this.readyState === 4) {
          if (this.status === 200) {
            res({
              json: () => Promise.resolve(JSON.parse(this.responseText)),
            });
          } else if (this.status === 409) {
            try {
              const { progress } = JSON.parse(this.responseText);
              showProgress(progress);
            } catch (error) {
              console.error(error);
              showProgress(false);
            }

            setTimeout(() => {
              ajax.onreadystatechange = null;
              ping().then((response) => res(response));
            }, 4000);
          } else {
            rej(new Error(`HTTP error response status code: ${this.status}`));
          }
        }
      };

      ajax.open("POST", window.postsBridgeAjaxSync.url, true);
      ajax.setRequestHeader(
        "Content-Type",
        "application/x-www-form-urlencoded"
      );

      ajax.send(body.toString());
    }).finally(() => showProgress(false));
  };

  const sync = () => {
    if (loading || error) return;
    setLoading(true);

    const body = new URLSearchParams();
    body.set("_ajax_nonce", window.postsBridgeAjaxSync.nonce);
    body.set("action", window.postsBridgeAjaxSync.actions.sync);

    if (postType) {
      body.set("post_type", postType);
    }

    return new Promise((res, rej) => {
      const ajax = new XMLHttpRequest();
      ajax.timeout = 1000 * 60;

      ajax.onreadystatechange = function () {
        if (this.readyState === 4) {
          if (this.status === 200) {
            res({
              json: () => Promise.resolve(JSON.parse(this.responseText)),
            });
          } else if (this.status === 409 || this.status === 0) {
            try {
              const { progress } = JSON.parse(this.responseText);
              showProgress(progress);
            } catch (error) {
              console.error(error);
              showProgress(false);
            }

            setTimeout(() => {
              ajax.onreadystatechange = () => {};
              ping().then((response) => res(response));
            }, 4000);
          } else {
            rej(new Error(`HTTP error response status code: ${this.status}`));
          }
        }
      };

      ajax.open("POST", window.postsBridgeAjaxSync.url, true);
      ajax.setRequestHeader(
        "Content-Type",
        "application/x-www-form-urlencoded"
      );

      ajax.send(body.toString());
      setTimeout(
        () => showProgress({ index: 0, total: 100, post_type: postType }),
        500
      );
    })
      .catch(() => setError(__("AJAX synchronization error", "posts-bridge")))
      .finally(() => {
        showProgress(false);
        setLoading(false);
      });
  };

  return sync;
}

function showProgress(status) {
  const root = document.getElementById("posts-bridge");
  if (!root) return;

  let el = root.querySelector("#syncProgress");

  if (!status) {
    if (el) {
      root.removeChild(el);
    }

    return;
  }

  if (!el) {
    el = document.createElement("div");
    el.id = "syncProgress";
    el.style.position = "absolute";
    el.style.zIndex = 20;
    el.style.top = "0px";
    el.style.bottom = "0px";
    el.style.left = "0px";
    el.style.right = "0px";
    el.style.margin = "auto";
    el.style.height = "fit-content";
    el.style.transform = "translateY(70px)";
    el.style.display = "flex";
    el.style.flexDirection = "column";
    el.style.alignItems = "center";
    el.style.gap = "0.5em";
    el.style.fontSize = "1.5rem";
    el.style.filter = "drop-shadow(2px 4px 4px #0004)";
    el.innerHTML = `<label for="ajaxProgress">${__("Synchronizing", "posts-bridge")} <b>${status.post_type}</b> post type</label><progress name="ajaxProgress"></progress>`;

    root.appendChild(el);
    el.scrollIntoView({
      behavior: "smooth",
      block: "center",
      inline: "center",
    });

    const spinner = document.getElementById("postsBridgeSpinner");
    if (spinner) {
      spinner.style.backdropFilter = "blur(2px)";
    }
  }

  const progress = el.querySelector("progress");
  progress.setAttribute("value", status.index);
  progress.setAttribute("max", status.total);
  progress.textContent = (status.index / status.total) * 100 + "%";
}
