// source
import { useLoading } from "./Loading";
import { useError } from "./Error";

const { createContext, useContext, useEffect, useState } = wp.element;
const apiFetch = wp.apiFetch;
const { __ } = wp.i18n;

const CustomPostTypesContext = createContext({
  postTypes: [],
  postType: null,
  setPostType: () => {},
  data: null,
  submit: () => {},
});

export default function CustomPostTypesProvider({ children }) {
  const [loading, setLoading] = useLoading();
  const [error, setError] = useError();

  const [postTypes, setPostTypes] = useState([]);
  const [postType, setPostType] = useState(null);
  const [data, setData] = useState(null);

  useEffect(() => {
    fetchPostTypes();
  }, []);

  useEffect(() => {
    if (!postType) {
      setData(null);
    } else {
      fetchData(postType);
    }
  }, [postType]);

  const fetchPostTypes = () => {
    if (loading || error) return;

    setLoading(true);

    apiFetch({
      path: "posts-bridge/v1/post_types",
    })
      .then(setPostTypes)
      .catch(() => setError(__("Loading post types error", "posts-bridge")))
      .finally(() => setLoading(false));
  };

  const fetchData = (postType) => {
    return apiFetch({
      path: "posts-bridge/v1/post_types/" + postType,
    })
      .then(setData)
      .catch(() => setError(__("Loading post type error", "posts-bridge")));
  };

  const register = (data) => {
    if (loading || error) return Promise.resolve(false);
    setLoading(true);

    return apiFetch({
      path: "posts-bridge/v1/post_types/" + data.name,
      method: "POST",
      data,
    })
      .then((data) => {
        setData(data);
        fetchPostTypes();
        return true;
      })
      .catch(() => setError(__("Post type submit error", "posts-bridge")))
      .finally(() => setLoading(false));
  };

  const unregister = (postType) => {
    if (loading || error) return Promise.resolve(false);
    setLoading(true);

    return apiFetch({
      path: "posts-bridge/v1/post_types/" + postType,
      method: "DELETE",
    })
      .then(() => {
        setData(null);
        fetchPostTypes();
        return true;
      })
      .catch(() =>
        setError(__("Post type unregistration error", "posts-bridge"))
      )
      .finally(() => setLoading(false));
  };

  return (
    <CustomPostTypesContext.Provider
      value={{
        postType,
        setPostType,
        postTypes,
        data,
        register,
        unregister,
      }}
    >
      {children}
    </CustomPostTypesContext.Provider>
  );
}

export function useCustomPostType() {
  const { postType, setPostType } = useContext(CustomPostTypesContext);
  return [postType, setPostType];
}

export function useCustomPostTypes() {
  const { postTypes } = useContext(CustomPostTypesContext);
  return postTypes || [];
}

export function useCustomPostTypeData(name) {
  const { postType, setPostType, data } = useContext(CustomPostTypesContext);
  if (postType !== name) {
    setPostType(name);
  }

  return data;
}

export function useRegisterCustomPostType() {
  const { register } = useContext(CustomPostTypesContext);
  return (data) => register(data);
}

export function useUnregisterCustomPostType() {
  const { unregister } = useContext(CustomPostTypesContext);
  return (name) => unregister(name);
}
