# Use an official Python runtime as a parent image
FROM python:3.11-slim

# Install system dependencies
RUN apt-get update && apt-get install -y \
    gcc \
    g++ \
    python3-dev \
    python3-pip \
    git \
    cmake \
    libpython3-dev \
    && rm -rf /var/lib/apt/lists/*

# Set the working directory in the container
WORKDIR /app

# Copy the current directory contents into the container
COPY . .

# Set Python path to include the current directory
ENV PYTHONPATH=/app

# Install dependencies with specific pyarrow version
RUN pip install --no-cache-dir pyarrow==14.0.1 && \
    pip install --no-cache-dir -r requirements.txt

# Expose the application port
EXPOSE 9999

# Command to run the application
CMD ["uvicorn", "nodelistener:app", "--host", "0.0.0.0", "--port", "9999"]