FROM python:3.11-slim

WORKDIR /app

#Install system dependencies
RUN apt-get update && apt-get install -y \
    curl \
    && rm -rf /var/lib/apt/lists/*

#Copy requirements
COPY requirements.txt .

#Install Python dependencies
RUN pip install --no-cache-dir -r requirements.txt

#Copy orchestrator code
COPY . .

#Expose FastAPI port
EXPOSE 8000

#Default command: run FastAPI
CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "8000"]
