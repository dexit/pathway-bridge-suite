const {
  Button,
  Modal,
  __experimentalItemGroup: ItemGroup,
  __experimentalItem: Item,
} = wp.components;
const { useState, useMemo } = wp.element;
const { __ } = wp.i18n;

export default function FormBridges({ bridges, setBridges }) {
  const [open, setOpen] = useState(false);

  const orderedBridges = useMemo(() => {
    return bridges.sort((a, b) => {
      if (isNaN(a.order)) return 1;
      if (isNaN(b.order)) return -1;
      return a.order - b.order;
    });
  }, [bridges]);

  const move = (from, to) => {
    const bridge = orderedBridges[from];

    const slicedBridges = orderedBridges
      .slice(0, from)
      .concat(orderedBridges.slice(from + 1));

    const newBridges = slicedBridges
      .slice(0, to)
      .concat(bridge)
      .concat(slicedBridges.slice(to));

    newBridges.forEach((bridge, index) => (bridge.order = index));
    setBridges(newBridges);
  };

  const setFailure = (index, policy) => {
    const newBridges = bridges.map((bridge) => ({ ...bridge }));
    newBridges[index].allow_failure = !!policy;
    setBridges(newBridges);
  };

  return (
    <>
      <Button variant="secondary" onClick={() => setOpen(true)}>
        {__("Bridges", "forms-bridge")}
      </Button>
      {open && (
        <Modal
          title={__("Form's bridges", "forms-bridge")}
          onRequestClose={() => setOpen(false)}
        >
          <p
            style={{
              marginTop: "-3rem",
              position: "absolute",
              zIndex: 1,
            }}
          >
            {__(
              "Manage the form's bridge chain order and its submission failure policies",
              "forms-bridge"
            )}
          </p>
          <div
            style={{
              marginTop: "2rem",
              width: "680px",
              maxWidth: "80vw",
              minHeight: "125px",
              height: "calc(100% - 2rem)",
              display: "flex",
              flexDirection: "column",
              borderTop: "1px solid",
              borderBottom: "1px solid",
            }}
          >
            <div
              style={{
                flex: 1,
                overflowY: "auto",
                display: "flex",
                flexDirection: "column",
              }}
            >
              <ItemGroup
                size="large"
                isSeparated
                style={{ maxHeight: "calc(100% - 68px)", overflowY: "auto" }}
              >
                {orderedBridges.map((bridge, i) => (
                  <Item key={bridge.name + i}>
                    <BridgeStep
                      index={i}
                      name={bridge.name}
                      failure={bridge.allow_failure}
                      setFailure={(policy) => setFailure(i, policy)}
                      move={(direction) => move(i, i + direction)}
                      isLast={i === bridges.length - 1}
                    />
                  </Item>
                ))}
              </ItemGroup>
            </div>
          </div>
        </Modal>
      )}
    </>
  );
}

function BridgeStep({ index, name, failure, setFailure, move, isLast }) {
  return (
    <div style={{ display: "flex", alignItems: "center" }}>
      <div style={{ flex: 1 }}>
        {index + 1}. <b>{name}</b>
      </div>
      <div
        style={{ marginRight: "1em", cursor: "pointer", minWidth: "180px" }}
        onClick={() => setFailure(!failure)}
      >
        <span
          role="button"
          size="compact"
          style={{
            fontSize: "1.25em",
            margin: "0 0.5em 0 1em",
            cursor: "pointer",
          }}
        >
          {failure === false ? "🔴" : "🟢"}
        </span>
        {failure === false ? "Stop on failure" : "Continue on failure"}
      </div>
      <div
        style={{
          display: "inline-flex",
          alignItems: "center",
          gap: "0.45em",
          padding: "0 0.45em 0 0.75em",
        }}
      >
        <Button
          size="compact"
          variant="secondary"
          onClick={() => move(-1)}
          style={{ width: "32px" }}
          disabled={!index}
          __next40pxDefaultSize
        >
          <span
            title={__("Move up", "forms-bridge")}
            style={{ fontSize: "1.35em", marginLeft: "-4px" }}
          >
            ⬆
          </span>
        </Button>
        <Button
          size="compact"
          variant="secondary"
          onClick={() => move(+1)}
          style={{ width: "32px" }}
          disabled={isLast}
          label={__("Move down", "forms-bridge")}
          __next40pxDefaultSize
        >
          <span
            title={__("Move down", "forms-bridge")}
            style={{ fontSize: "1.35em", marginLeft: "-4px" }}
          >
            ⬇
          </span>
        </Button>
      </div>
    </div>
  );
}
