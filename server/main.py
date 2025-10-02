from fastapi import FastAPI

app = FastAPI()   # ⚡ Обязательно переменная с именем "app"

@app.get("/")
def root():
    return {"message": "Привет! Сервер работает 🚀"}
@app.get("/forecast")
def get_forecast(city: str, date: str):
    return {
        "city": city,
        "date": date,
        "forecast": {
            "rain_probability": 0.7,
            "temperature": {"min": 20, "max": 28},
            "wind_speed": 10
        }
    }  #uvicorn main:app --reload    --- запуск кода