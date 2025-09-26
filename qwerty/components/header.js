import React from "react"

class Header extends React.Component {
  render() {
    return (
      <header className="header">
        {this.props.title}
      </header>
    )
  }
}
import React from "react";

const Header = ({ title }) => {
  return <h2>{title}</h2>;
};




export default Header
