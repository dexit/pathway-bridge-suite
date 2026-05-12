// source
import useAllRCPTs from "../../hooks/useRemoteCPTs";
import { usePostTypes } from "../../hooks/useGeneral";
import { useBridges, useRemoteCPTs } from "../../hooks/useAddon";
import ApiSchemaProvider from "../../providers/ApiSchema";
import { useSchemas } from "../../providers/Schemas";
import Bridge from "../Bridge";
import NewBridge from "../Bridge/NewBridge";
import TabTitle from "../TabTitle";
import AddIcon from "../icons/Add";

const { TabPanel } = wp.components;
const { useEffect, useMemo, useRef } = wp.element;
const { __ } = wp.i18n;

const CSS = `.bridges-tabs-panel>.components-tab-panel__tabs{overflow-x:auto;}
.bridges-tabs-panel>.components-tab-panel__tabs>button{flex-shrink:0;}`;

const DEFAULTS = {
  name: "bridge-" + Date.now(),
  backend: "",
  enabled: true,
  is_valid: true,
  field_mappers: [],
  tax_mappers: [],
};

export default function Bridges() {
  const { bridge: schema } = useSchemas();
  const [bridges, setBridges] = useBridges();
  const allRcpts = useAllRCPTs();
  const rcpts = useRemoteCPTs();
  const [postTypes] = usePostTypes();

  const freePostTypes = useMemo(() => {
    return postTypes.filter((p) => !allRcpts.has(p));
  }, [postTypes, allRcpts]);

  const tabs = useMemo(() => {
    return Array.from(rcpts)
      .map((name, index) => ({
        index,
        name: String(index),
        title: name,
        icon: <TabTitle name={name} />,
      }))
      .concat([
        {
          index: -1,
          name: "new",
          title: __("Add a bridge", "posts-bridge"),
          icon: (
            <div style={{ marginBottom: "-2px" }}>
              <AddIcon width="15" height="15" />
            </div>
          ),
        },
      ]);
  }, [rcpts]);

  const updateBridge = (index, data) => {
    if (index === -1) index = bridges.length;

    const newBridges = bridges
      .slice(0, index)
      .concat([{ ...DEFAULTS, ...data }])
      .concat(bridges.slice(index + 1, bridges.length));

    setBridges(newBridges);
  };

  const removeBridge = ({ post_type }) => {
    const index = bridges.findIndex((b) => b.post_type === post_type);
    const newBridges = bridges.slice(0, index).concat(bridges.slice(index + 1));
    setBridges(newBridges);
  };

  const copyBridge = (post_type) => {
    if (!freePostTypes.length) return;

    const i = bridges.findIndex((b) => b.post_type === post_type);
    const bridge = bridges[i];

    const newPostType = freePostTypes.find((p) => !allRcpts.has(p));
    if (!newPostType) {
      return;
    }

    const copy = {
      ...bridge,
      post_type: newPostType,
      field_mappers: JSON.parse(JSON.stringify(bridge.field_mappers || [])),
      tax_mappers: JSON.parse(JSON.stringify(bridge.tax_mappers || [])),
    };

    window.__wppbInvalidated = true;
    setBridges(bridges.concat(copy));
  };

  const style = useRef(document.createElement("style"));
  useEffect(() => {
    style.current.appendChild(document.createTextNode(CSS));
    document.head.appendChild(style.current);

    return () => {
      document.head.removeChild(style.current);
    };
  }, []);

  if (!schema) return null;

  return (
    <div style={{ width: "100%" }}>
      <h3 style={{ marginTop: 0, fontSize: "13px" }}>
        {__("Bridges", "posts-bridge")}
      </h3>
      <TabPanel tabs={tabs} className="bridges-tabs-panel">
        {(tab) => {
          const bridge = bridges[tab.index];

          return (
            <ApiSchemaProvider bridge={bridge}>
              {(!bridge && (
                <NewBridge
                  add={(data) => updateBridge(tab.index, data)}
                  schema={schema}
                />
              )) || (
                <Bridge
                  data={bridge}
                  schema={schema}
                  remove={removeBridge}
                  update={(data) => updateBridge(tab.index, data)}
                  copy={() => copyBridge(bridge.post_type)}
                />
              )}
            </ApiSchemaProvider>
          );
        }}
      </TabPanel>
    </div>
  );
}
