import useTab from "../hooks/useTab";
import { useBackends } from "../hooks/useHttp";

const apiFetch = wp.apiFetch;
const { createContext, useContext, useRef, useState, useMemo, useEffect } =
  wp.element;

const ApiSchemaContext = createContext([]);

export default function ApiSchemaProvider({ children, bridge }) {
  const [addon] = useTab();
  const [backends] = useBackends();

  const endpoints = useRef(new Map()).current;
  const schemas = useRef(new Map()).current;

  const [, endpointUpdates] = useState(0);
  const [, schemaUpdates] = useState(0);

  const backend = useMemo(
    () => backends.find(({ name }) => bridge?.backend === name),
    [backends, bridge]
  );

  const endpointsKey = useMemo(() => {
    if (!backend?.name) return "";

    return JSON.stringify({
      addon,
      method: bridge?.method || "",
      backend: backend?.name,
    });
  }, [addon, bridge?.method, backend?.name]);

  const addEndpoints = (key, list) => {
    endpoints.set(key, list);
    endpointUpdates((i) => i + 1);
  };

  const fetchEndpoints = (key, method, backend) => {
    let done = false;
    const abortController = new AbortController();

    apiFetch({
      path: `posts-bridge/v1/${addon}/backend/endpoints`,
      method: "POST",
      data: { method, backend },
      signal: abortController.signal,
    })
      .then((endpoints) => addEndpoints(key, endpoints))
      .catch((err) => {
        if (DOMException.ABORT_ERR !== err.code) {
          addEndpoints(key, []);
        }
      })
      .finally(() => (done = true));

    return () => {
      !done && abortController.abort();
    };
  };

  const endpointsTimeout = useRef();
  useEffect(() => {
    if (!backend || !bridge?.method || endpoints.get(endpointsKey)) {
      return;
    }

    let abort;
    endpointsTimeout.current = setTimeout(() => {
      abort = fetchEndpoints(endpointsKey, bridge.method, backend);
    }, 500);

    return () => {
      clearTimeout(endpointsTimeout.current);
      abort && abort();
    };
  }, [endpointsKey, backend, bridge?.method]);

  const schemaKey = useMemo(() => {
    if (!bridge?.method || !backend?.name) return "";

    return JSON.stringify({
      addon,
      method: bridge.method,
      endpoint: bridge.endpoint || "/",
      backend: backend.name,
    });
  }, [addon, bridge?.method, bridge?.endpoint, backend?.name]);

  const addSchema = (key, schema) => {
    schemas.set(key, schema);
    schemaUpdates((i) => i + 1);
  };

  const fetchSchema = (key, endpoint, method, backend) => {
    let done = false;
    const abortController = new AbortController();

    apiFetch({
      path: `posts-bridge/v1/${addon}/backend/endpoint/schema`,
      method: "POST",
      data: { endpoint, method, backend },
      signal: abortController.signal,
    })
      .then((schema) => addSchema(key, schema))
      .catch((err) => {
        if (DOMException.ABORT_ERR !== err.code) {
          addSchema(key, []);
        }
      })
      .finally(() => (done = true));

    return () => {
      !done && abortController.abort();
    };
  };

  const schemaTimeout = useRef();
  useEffect(() => {
    if (!backend || !bridge?.endpoint || schemas.get(schemaKey)) {
      return;
    }

    let abort;
    schemaTimeout.current = setTimeout(() => {
      abort = fetchSchema(
        schemaKey,
        bridge.endpoint || "/",
        bridge.method,
        backend
      );
    }, 500);

    return () => {
      clearTimeout(schemaTimeout.current);
      abort && abort();
    };
  }, [schemaKey, bridge, backend]);

  return (
    <ApiSchemaContext.Provider
      value={{
        schema: schemas.get(schemaKey),
        endpoints: endpoints.get(endpointsKey),
      }}
    >
      {children}
    </ApiSchemaContext.Provider>
  );
}

export function useApiFields() {
  const { schema } = useContext(ApiSchemaContext);
  return schema || [];
}

export function useApiEndpoints() {
  const { endpoints } = useContext(ApiSchemaContext);
  return endpoints || [];
}
