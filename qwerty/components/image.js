import React from "react";

const Image = ({ src, alt }) => {
  return <img src={src} alt={alt || "image"} style={{ width: "200px" }} />;
};

export default Image;

