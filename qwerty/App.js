class App extends React.Component {
  helpText = "Бобинь";

  inputClick = () => { console.log("Clicked"); }
  mouseOver = () => { console.log("Mouse Over"); }

  render() {
    return (
      React.createElement("div", null,
        React.createElement("h1", null, this.helpText),
        React.createElement("input", {
          placeholder: this.helpText,
          onClick: this.inputClick,
          onMouseEnter: this.mouseOver
        }),
        React.createElement("p", null, this.helpText === "Бобинь" ? "yes" : "no"),
        React.createElement("img", { src: "img/qqq.jpg", alt: "Моя картинка", width: "200" })
      )
    );
  }
}

const root = ReactDOM.createRoot(document.getElementById("app"));
root.render(React.createElement(App));

