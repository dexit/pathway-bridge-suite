// source
import ErrorBoundary from "./ErrorBoundary";
import ErrorProvider from "./providers/Error";
import LoadingProvider from "./providers/Loading";
import SchemasProvider from "./providers/Schemas";
import SettingsProvider from "./providers/Settings";
import Settings from "./components/Settings";
import SaveButton from "./components/SaveButton";
import CustomPostTypesProvider from "./providers/CustomPostTypes";

const { createRoot, useRef } = wp.element;
const { __experimentalHeading: Heading } = wp.components;

function App() {
  const adminbar = useRef(
    document.getElementById("wpadminbar").offsetHeight
  ).current;

  return (
    <div
      id="posts-bridge"
      style={{ position: "relative", minHeight: `calc(100vh - ${adminbar}px)` }}
    >
      <ErrorBoundary
        fallback={
          <div
            style={{
              height: "50vh",
              paddingLeft: "1em",
              display: "flex",
              justifyContent: "center",
              alignItems: "center",
            }}
          >
            <h1>Why do you do this to me? 😩</h1>
          </div>
        }
      >
        <ErrorProvider>
          <LoadingProvider>
            <CustomPostTypesProvider>
              <SettingsProvider>
                <SchemasProvider>
                  <div
                    style={{
                      display: "flex",
                      justifyContent: "space-between",
                      paddingTop: "calc(16px)",
                      alignItems: "baseline",
                    }}
                  >
                    <Heading level={1}>Posts Bridge</Heading>
                    <SaveButton />
                  </div>
                  <Settings />
                </SchemasProvider>
              </SettingsProvider>
            </CustomPostTypesProvider>
          </LoadingProvider>
        </ErrorProvider>
      </ErrorBoundary>
    </div>
  );
}

wp.domReady(() => {
  const root = createRoot(document.getElementById("posts-bridge"));
  root.render(<App />);
});
